<?php
/**
 * @var \App\View\AppView $this
 * @var \Crustum\Tessera\Model\Entity\Client|null $client
 * @var list<\Crustum\Tessera\Scope> $scopes
 * @var string $authToken
 */
?>
<div class="tessera device authorization">
    <h3><?= h(__('Device authorization')) ?></h3>
    <p>
        <?= h(__('{0} is requesting access to your account.', $client->name ?? '')) ?>
    </p>
    <?php if (!empty($scopes)): ?>
        <ul>
            <?php foreach ($scopes as $scope): ?>
                <li><?= h($scope->description !== '' ? $scope->description : $scope->id) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?= $this->Form->create(null, ['url' => ['_name' => 'tessera:device.authorizations.approve']]) ?>
        <?= $this->Form->hidden('auth_token', ['value' => $authToken]) ?>
        <?= $this->Form->button(__('Approve')) ?>
    <?= $this->Form->end() ?>
</div>
