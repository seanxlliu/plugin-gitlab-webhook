<?php

require_once 'tests/units/Base.php';

use Kanboard\Event\GenericEvent;
use Kanboard\Plugin\GitlabMergeHook\WebhookHandler;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Model\TaskFinderModel;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\ProjectUserRoleModel;
use Kanboard\Model\UserModel;
use Kanboard\Core\Security\Role;

class WebhookHandlerTest extends Base
{
    public function testProcessMessage()
    {
        $handler = new WebhookHandler($this->container);
        $project = array('web_url' => 'http://localhost');

        $message = 'Text:\r\n\r\n![My image 1](/uploads/1a4d374af5ba51d8246b589f8932de66/img1.jpg)
                    Text:\r\n\r\n![My image 2](/uploads/1a4d374af5ba51d8246b589f8932de66/img2.jpg)
                    Text:\r\n\r\n[My link](/uploads/1a4d374af5ba51d8246b589f8932de66/img2.jpg)
                    Link: /uploads/stuff';

        $expected = 'Text:\r\n\r\n![My image 1](http://localhost/uploads/1a4d374af5ba51d8246b589f8932de66/img1.jpg)
                    Text:\r\n\r\n![My image 2](http://localhost/uploads/1a4d374af5ba51d8246b589f8932de66/img2.jpg)
                    Text:\r\n\r\n[My link](http://localhost/uploads/1a4d374af5ba51d8246b589f8932de66/img2.jpg)
                    Link: /uploads/stuff';

        $this->assertEquals('My message', $handler->processMessage('My message', $project));
        $this->assertEquals($expected, $handler->processMessage($message, $project));
    }

    public function testGetEventType()
    {
        $g = new WebhookHandler($this->container);

        $this->assertEquals(WebhookHandler::TYPE_PUSH, $g->getType(json_decode(file_get_contents(__DIR__.'/fixtures/gitlab_push.json'), true)));
        $this->assertEquals(WebhookHandler::TYPE_ISSUE, $g->getType(json_decode(file_get_contents(__DIR__.'/fixtures/gitlab_issue_opened.json'), true)));
        $this->assertEquals(WebhookHandler::TYPE_COMMENT, $g->getType(json_decode(file_get_contents(__DIR__.'/fixtures/gitlab_comment_created.json'), true)));
        $this->assertEquals('', $g->getType(array()));
    }

    public function testHandleCommit()
    {
        $g = new WebhookHandler($this->container);
        $p = new ProjectModel($this->container);
        $tc = new TaskCreationModel($this->container);
        $tf = new TaskFinderModel($this->container);

        $this->assertEquals(1, $p->create(array('name' => 'test')));
        $g->setProjectId(1);

        $this->container['dispatcher']->addListener(WebhookHandler::EVENT_COMMIT, array($this, 'onCommit'));

        $event = json_decode(file_get_contents(__DIR__.'/fixtures/gitlab_push.json'), true);

        // No task
        $this->assertFalse($g->handleCommit($event['commits'][0]));

        // Create task with the wrong id
        $this->assertEquals(1, $tc->create(array('title' => 'test1', 'project_id' => 1)));
        $this->assertFalse($g->handleCommit($event['commits'][0]));

        // Create task with the right id
        $this->assertEquals(2, $tc->create(array('title' => 'test2', 'project_id' => 1)));
        $this->assertTrue($g->handleCommit($event['commits'][0]));

        $called = $this->container['dispatcher']->getCalledListeners();
        $this->assertArrayHasKey(WebhookHandler::EVENT_COMMIT.'.WebhookHandlerTest::onCommit', $called);
    }

    public function testHandleIssueOpened()
    {
        $g = new WebhookHandler($this->container);
        $g->setProjectId(1);

        $this->container['dispatcher']->addListener(WebhookHandler::EVENT_ISSUE_OPENED, array($this, 'onOpen'));

        $event = json_decode(file_get_contents(__DIR__.'/fixtures/gitlab_issue_opened.json'), true);
        $this->assertTrue($g->handleIssueOpened($event['object_attributes'], $event['project']));

        $called = $this->container['dispatcher']->getCalledListeners();
        $this->assertArrayHasKey(WebhookHandler::EVENT_ISSUE_OPENED.'.WebhookHandlerTest::onOpen', $called);
    }

    public function testHandleIssueReopened()
    {
        $g = new WebhookHandler($this->container);
        $p = new ProjectModel($this->container);
        $tc = new TaskCreationModel($this->container);
        $tf = new TaskFinderModel($this->container);

        $this->assertEquals(1, $p->create(array('name' => 'test')));
        $g->setProjectId(1);

        $this->container['dispatcher']->addListener(WebhookHandler::EVENT_ISSUE_REOPENED, array($this, 'onReopen'));

        $event = json_decode(file_get_contents(__DIR__.'/fixtures/gitlab_issue_reopened.json'), true);

        // Issue not there
        $this->assertFalse($g->handleIssueReopened($event['object_attributes']));

        $called = $this->container['dispatcher']->getCalledListeners();
        $this->assertEmpty($called);

        $this->assertEquals(1, $tc->create(array('title' => 'A', 'project_id' => 1, 'reference' => 1268888)));
        $task = $tf->getByReference(1, 1268888);
        $this->assertTrue($g->handleIssueReopened($event['object_attributes']));

        $called = $this->container['dispatcher']->getCalledListeners();
        $this->assertArrayHasKey(WebhookHandler::EVENT_ISSUE_REOPENED.'.WebhookHandlerTest::onReopen', $called);
    }


    public function testHandleIssueClosed()
    {
        $g = new WebhookHandler($this->container);
        $p = new ProjectModel($this->container);
        $tc = new TaskCreationModel($this->container);
        $tf = new TaskFinderModel($this->container);

        $this->assertEquals(1, $p->create(array('name' => 'test')));
        $g->setProjectId(1);

        $this->container['dispatcher']->addListener(WebhookHandler::EVENT_ISSUE_CLOSED, array($this, 'onClose'));

        $event = json_decode(file_get_contents(__DIR__.'/fixtures/gitlab_issue_closed.json'), true);

        // Issue not there
        $this->assertFalse($g->handleIssueClosed($event['object_attributes']));

        $called = $this->container['dispatcher']->getCalledListeners();
        $this->assertEmpty($called);

        // Create a task with the issue reference
        $this->assertEquals(1, $tc->create(array('title' => 'A', 'project_id' => 1, 'reference' => 1268888)));
        $task = $tf->getByReference(1, 1268888);
        $this->assertNotEmpty($task);

        $task = $tf->getByReference(2, 1268888);
        $this->assertEmpty($task);

        $this->assertTrue($g->handleIssueClosed($event['object_attributes']));

        $called = $this->container['dispatcher']->getCalledListeners();
        $this->assertArrayHasKey(WebhookHandler::EVENT_ISSUE_CLOSED.'.WebhookHandlerTest::onClose', $called);
    }

    public function testCommentCreatedWithNoUser()
    {
        $this->container['dispatcher']->addListener(WebhookHandler::EVENT_ISSUE_COMMENT, array($this, 'onCommentCreatedWithNoUser'));

        $p = new ProjectModel($this->container);
        $this->assertEquals(1, $p->create(array('name' => 'foobar')));

        $tc = new TaskCreationModel($this->container);
        $this->assertEquals(1, $tc->create(array('title' => 'boo', 'reference' => 1268888, 'project_id' => 1)));

        $g = new WebhookHandler($this->container);
        $g->setProjectId(1);

        $this->assertNotFalse($g->parsePayload(
            json_decode(file_get_contents(__DIR__.'/fixtures/gitlab_comment_created.json'), true)
        ));
    }

    public function testCommentCreatedWithNotMember()
    {
        $this->container['dispatcher']->addListener(WebhookHandler::EVENT_ISSUE_COMMENT, array($this, 'onCommentCreatedWithNotMember'));

        $p = new ProjectModel($this->container);
        $this->assertEquals(1, $p->create(array('name' => 'foobar')));

        $tc = new TaskCreationModel($this->container);
        $this->assertEquals(1, $tc->create(array('title' => 'boo', 'reference' => 1268888, 'project_id' => 1)));

        $u = new UserModel($this->container);
        $this->assertEquals(2, $u->create(array('username' => 'kanboard')));

        $g = new WebhookHandler($this->container);
        $g->setProjectId(1);

        $this->assertNotFalse($g->parsePayload(
            json_decode(file_get_contents(__DIR__.'/fixtures/gitlab_comment_created.json'), true)
        ));
    }

    public function testCommentCreatedWithUser()
    {
        $this->container['dispatcher']->addListener(WebhookHandler::EVENT_ISSUE_COMMENT, array($this, 'onCommentCreatedWithUser'));

        $p = new ProjectModel($this->container);
        $this->assertEquals(1, $p->create(array('name' => 'foobar')));

        $tc = new TaskCreationModel($this->container);
        $this->assertEquals(1, $tc->create(array('title' => 'boo', 'reference' => 1268888, 'project_id' => 1)));

        $u = new UserModel($this->container);
        $this->assertEquals(2, $u->create(array('username' => 'kanboard')));

        $pp = new ProjectUserRoleModel($this->container);
        $this->assertTrue($pp->addUser(1, 2, Role::PROJECT_MEMBER));

        $g = new WebhookHandler($this->container);
        $g->setProjectId(1);

        $this->assertNotFalse($g->parsePayload(
            json_decode(file_get_contents(__DIR__.'/fixtures/gitlab_comment_created.json'), true)
        ));
    }

    public function onOpen(GenericEvent $event)
    {
        $data = $event->getAll();
        $this->assertEquals(1, $data['project_id']);
        $this->assertEquals(1268888, $data['reference']);
        $this->assertEquals('Bug', $data['title']);
        $this->assertEquals("There is a bug somewhere.\r\n\r\n![My image 1](https://gitlab.com/kanboard/test-webhook/uploads/1a4d374af5ba51d8246b589f8932de66/img1.jpg)\n\n[Gitlab Issue](https://gitlab.com/kanboard/test-webhook/issues/5)", $data['description']);
    }

    public function onReopen(GenericEvent $event)
    {
        $data = $event->getAll();
        $this->assertEquals(1, $data['project_id']);
        $this->assertEquals(1, $data['task_id']);
        $this->assertEquals(1268888, $data['reference']);
    }
    public function onClose(GenericEvent $event)
    {
        $data = $event->getAll();
        $this->assertEquals(1, $data['project_id']);
        $this->assertEquals(1, $data['task_id']);
        $this->assertEquals(1268888, $data['reference']);
    }

    public function onCommit(GenericEvent $event)
    {
        $data = $event->getAll();
        $this->assertEquals(1, $data['project_id']);
        $this->assertEquals(2, $data['task_id']);
        $this->assertEquals('test2', $data['title']);
        $this->assertEquals("Fix bug #2\n\n[Commit made by @Fred on Gitlab](https://gitlab.com/kanboard/test-webhook/commit/48aafa75eef9ad253aa254b0c82c987a52ebea78)", $data['comment']);
        $this->assertEquals("Fix bug #2", $data['commit_message']);
        $this->assertEquals('https://gitlab.com/kanboard/test-webhook/commit/48aafa75eef9ad253aa254b0c82c987a52ebea78', $data['commit_url']);
    }

    public function onCommentCreatedWithNoUser(GenericEvent $event)
    {
        $data = $event->getAll();
        $this->assertEquals(1, $data['project_id']);
        $this->assertEquals(1, $data['task_id']);
        $this->assertEquals(0, $data['user_id']);
        $this->assertEquals(3972168, $data['reference']);
        $this->assertEquals("Super comment! ![My image 1](https://gitlab.com/kanboard/test-webhook/uploads/1a4d374af5ba51d8246b589f8932de66/img1.jpg)\n\n[By @kanboard on Gitlab](https://gitlab.com/kanboard/test-webhook/issues/5#note_3972168)", $data['comment']);
    }

    public function onCommentCreatedWithNotMember(GenericEvent $event)
    {
        $data = $event->getAll();
        $this->assertEquals(1, $data['project_id']);
        $this->assertEquals(1, $data['task_id']);
        $this->assertEquals(0, $data['user_id']);
        $this->assertEquals(3972168, $data['reference']);
        $this->assertEquals("Super comment! ![My image 1](https://gitlab.com/kanboard/test-webhook/uploads/1a4d374af5ba51d8246b589f8932de66/img1.jpg)\n\n[By @kanboard on Gitlab](https://gitlab.com/kanboard/test-webhook/issues/5#note_3972168)", $data['comment']);
    }

    public function onCommentCreatedWithUser(GenericEvent $event)
    {
        $data = $event->getAll();
        $this->assertEquals(1, $data['project_id']);
        $this->assertEquals(1, $data['task_id']);
        $this->assertEquals(2, $data['user_id']);
        $this->assertEquals(3972168, $data['reference']);
        $this->assertEquals("Super comment! ![My image 1](https://gitlab.com/kanboard/test-webhook/uploads/1a4d374af5ba51d8246b589f8932de66/img1.jpg)\n\n[By @kanboard on Gitlab](https://gitlab.com/kanboard/test-webhook/issues/5#note_3972168)", $data['comment']);
    }
}
