<h3><i class="fa fa-gitlab fa-fw" aria-hidden="true"></i><?= t('Gitlab webhooks') ?></h3>
<div class="listing">
<input type="text" class="auto-select" readonly="readonly" value="<?= $this->url->href('WebhookController', 'handler', array('plugin' => 'GitlabMergeHook', 'token' => $webhook_token, 'project_id' => $project['id']), false, '', true) ?>"/><br/>
<p class="form-help"><a href="http://192.168.3.11:10080/xl/gitlabwebhook" target="_blank"><?= t('Help on Gitlab webhooks') ?></a></p>
</div>
