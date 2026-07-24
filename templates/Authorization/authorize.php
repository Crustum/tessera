<?php
/**
 * @var \App\View\AppView $this
 * @var \Crustum\Tessera\Model\Entity\Client $client
 * @var \Crustum\Tessera\Contracts\OAuthenticatable $user
 * @var list<\Crustum\Tessera\Scope> $scopes
 * @var string $authToken
 */
?>
<div class="tessera authorization consent">
    <h3><?= h(__('Authorization consent')) ?></h3>
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
    <?= $this->Form->create(null, ['url' => ['_name' => 'tessera:authorizations.approve']]) ?>
        <?= $this->Form->hidden('auth_token', ['value' => $authToken]) ?>
        <?= $this->Form->button(__('Approve'), ['name' => 'approve']) ?>
    <?= $this->Form->end() ?>
</div>
