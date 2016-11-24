<?php

namespace Kanboard\Plugin\GitlabMergeHook;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Translator;

class Plugin extends Base
{
    public function initialize()
    {
        $this->actionManager->getAction('\Kanboard\Action\CommentCreation')->addEvent(WebhookHandler::EVENT_ISSUE_COMMENT);
        $this->actionManager->getAction('\Kanboard\Action\CommentCreation')->addEvent(WebhookHandler::EVENT_COMMIT);
        $this->actionManager->getAction('\Kanboard\Action\CommentCreation')->addEvent(WebhookHandler::EVENT_MERGEREQ_MERGE);
        $this->actionManager->getAction('\Kanboard\Action\TaskClose')->addEvent(WebhookHandler::EVENT_COMMIT);
        $this->actionManager->getAction('\Kanboard\Action\TaskClose')->addEvent(WebhookHandler::EVENT_ISSUE_CLOSED);
        $this->actionManager->getAction('\Kanboard\Action\TaskCreation')->addEvent(WebhookHandler::EVENT_ISSUE_OPENED);
        $this->actionManager->getAction('\Kanboard\Action\TaskOpen')->addEvent(WebhookHandler::EVENT_ISSUE_REOPENED);
        $this->actionManager->getAction('\Kanboard\Action\TaskAssignColorColumn')->addEvent(WebhookHandler::EVENT_MERGEREQ_MERGE);
         
        $this->template->hook->attach('template:project:integrations', 'GitlabWebhook:project/integrations');
        $this->route->addRoute('/webhook/gitlab/:project_id/:token', 'WebhookController', 'handler', 'GitlabWebhook');
        $this->applicationAccessMap->add('WebhookController', 'handler', Role::APP_PUBLIC);
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');

        $this->eventManager->register(WebhookHandler::EVENT_COMMIT, t('Gitlab commit received'));
        $this->eventManager->register(WebhookHandler::EVENT_ISSUE_OPENED, t('Gitlab issue opened'));
        $this->eventManager->register(WebhookHandler::EVENT_ISSUE_CLOSED, t('Gitlab issue closed'));
        $this->eventManager->register(WebhookHandler::EVENT_ISSUE_REOPENED, t('Gitlab issue reopened'));
        $this->eventManager->register(WebhookHandler::EVENT_ISSUE_COMMENT, t('Gitlab issue comment created'));
        $this->eventManager->register(WebhookHandler::EVENT_MERGEREQ_MERGE, t('Gitlab merge request merged'));
    }

    public function getPluginName()
    {
        return 'Gitlab Webhook (Merge Request)';
    }

    public function getPluginDescription()
    {
        return t('Bind Gitlab webhook events (merge request) to Kanboard automatic actions');
    }

    public function getPluginAuthor()
    {
        return 'Xiaolu Liu';
    }

    public function getPluginVersion()
    {
        return '0.0.1';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/seanxlliu/plugin-gitlab-webhook';
    }
}
