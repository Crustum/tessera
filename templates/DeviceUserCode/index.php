<?php
/**
 * @var \App\View\AppView $this
 */
?>
<div class="tessera device user-code">
    <h3><?= h(__('Enter device code')) ?></h3>
    <?= $this->Form->create(null, [
        'type' => 'get',
        'url' => ['_name' => 'tessera:device.authorizations.authorize'],
    ]) ?>
        <?= $this->Form->control('user_code', ['label' => __('User code')]) ?>
        <?= $this->Form->button(__('Continue')) ?>
    <?= $this->Form->end() ?>
</div>
