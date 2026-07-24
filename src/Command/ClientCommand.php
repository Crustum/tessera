<?php
declare(strict_types=1);

namespace Crustum\Tessera\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;
use Override;

/**
 * Create an OAuth client for issuing access tokens.
 */
class ClientCommand extends Command
{
    /**
     * @var \Cake\Console\Arguments
     */
    protected Arguments $args;

    /**
     * @var \Cake\Console\ConsoleIo
     */
    protected ConsoleIo $io;

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'tessera client';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Create a client for issuing access tokens';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->addOption('personal', [
                'help' => 'Create a personal access token client',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('password', [
                'help' => 'Create a password grant client',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('client', [
                'help' => 'Create a client credentials grant client',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('implicit', [
                'help' => 'Create an implicit grant client',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('device', [
                'help' => 'Create a device authorization grant client',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('name', [
                'help' => 'The name of the client',
                'default' => null,
            ])
            ->addOption('provider', [
                'help' => 'The name of the user provider',
                'default' => null,
            ])
            ->addOption('redirect_uri', [
                'help' => 'The URI to redirect to after authorization',
                'default' => null,
            ])
            ->addOption('public', [
                'help' => 'Create a public client (without secret)',
                'boolean' => true,
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $this->args = $args;
        $this->io = $io;

        $name = $args->getOption('name');
        if (!is_string($name) || $name === '') {
            $name = $io->ask(
                'What should we name the client?',
                (string)(Configure::read('App.name') ?? 'Tessera'),
            );
        }

        $clients = new ClientRepository();

        $client = match (true) {
            (bool)$args->getOption('personal') => $this->createPersonalAccessClient($clients, $name),
            (bool)$args->getOption('password') => $this->createPasswordClient($clients, $name),
            (bool)$args->getOption('client') => $clients->createClientCredentialsGrantClient($name),
            (bool)$args->getOption('implicit') => $this->createImplicitClient($clients, $name),
            (bool)$args->getOption('device') => $this->createDeviceCodeClient($clients, $name),
            default => $this->createAuthCodeClient($clients, $name),
        };

        $io->success('New client created successfully.');

        if ($client instanceof Client) {
            $io->out(sprintf('Client ID: %s', $client->id));
            if ($client->confidential()) {
                $io->out(sprintf('Client Secret: %s', $client->plainSecret));
                $io->warning("The client secret will not be shown again, so don't lose it!");
            }
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Create a personal access grant client.
     *
     * @param \Crustum\Tessera\ClientRepository $clients Client repository
     * @param string $name Client name
     */
    protected function createPersonalAccessClient(ClientRepository $clients, string $name): ?Client
    {
        $clients->createPersonalAccessGrantClient($name, $this->askProvider());

        return null;
    }

    /**
     * Create a password grant client.
     *
     * @param \Crustum\Tessera\ClientRepository $clients Client repository
     * @param string $name Client name
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    protected function createPasswordClient(ClientRepository $clients, string $name): Client
    {
        return $clients->createPasswordGrantClient(
            $name,
            $this->askProvider(),
            $this->askConfidential(false),
        );
    }

    /**
     * Create an implicit grant client.
     *
     * @param \Crustum\Tessera\ClientRepository $clients Client repository
     * @param string $name Client name
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    protected function createImplicitClient(ClientRepository $clients, string $name): Client
    {
        return $clients->createImplicitGrantClient($name, $this->askRedirectUris());
    }

    /**
     * Create a device authorization grant client.
     *
     * @param \Crustum\Tessera\ClientRepository $clients Client repository
     * @param string $name Client name
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    protected function createDeviceCodeClient(ClientRepository $clients, string $name): Client
    {
        return $clients->createDeviceAuthorizationGrantClient($name, $this->askConfidential(true));
    }

    /**
     * Create an authorization code grant client.
     *
     * @param \Crustum\Tessera\ClientRepository $clients Client repository
     * @param string $name Client name
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    protected function createAuthCodeClient(ClientRepository $clients, string $name): Client
    {
        $enableDeviceFlow = Tessera::$deviceCodeGrantEnabled
            && $this->askYesNo('Would you like to enable the device authorization flow for this client?', false);

        return $clients->createAuthorizationCodeGrantClient(
            $name,
            $this->askRedirectUris(),
            $this->askConfidential(true),
            null,
            $enableDeviceFlow,
        );
    }

    /**
     * Resolve the user provider from option or prompt.
     *
     * @return string
     */
    protected function askProvider(): string
    {
        $provider = $this->args->getOption('provider');
        if (is_string($provider) && $provider !== '') {
            return $provider;
        }

        $providers = array_map(strval(...), array_keys((array)Configure::read('Tessera.providers')));
        $default = (string)(Configure::read('Tessera.provider')
            ?? Configure::read('Tessera.TokenAuthenticator.providerName')
            ?? ($providers[0] ?? 'users'));

        if ($providers === []) {
            return $default;
        }

        return $this->io->askChoice(
            'Which user provider should this client use to retrieve users?',
            $providers,
            $default,
        );
    }

    /**
     * Resolve confidential vs public from `--public` or prompt.
     *
     * @param bool $default Default when prompting
     * @return bool
     */
    protected function askConfidential(bool $default): bool
    {
        if ($this->args->getOption('public')) {
            return false;
        }

        return $this->askYesNo('Would you like to make this client confidential?', $default);
    }

    /**
     * Resolve redirect URIs from option or prompt.
     *
     * @return list<string>
     */
    protected function askRedirectUris(): array
    {
        $base = rtrim((string)(Configure::read('App.fullBaseUrl') ?? 'http://localhost'), '/');
        $redirect = $this->args->getOption('redirect_uri') ?: $this->io->ask(
            'Where should we redirect the request after authorization?',
            $base . '/auth/callback',
        );

        return explode(',', (string)$redirect);
    }

    /**
     * Ask a yes/no question.
     *
     * @param string $question Question text
     * @param bool $default Default answer
     * @return bool
     */
    protected function askYesNo(string $question, bool $default): bool
    {
        $suffix = $default ? ' (Y/n)' : ' (y/N)';
        $answer = strtolower($this->io->ask($question . $suffix, $default ? 'y' : 'n'));

        return in_array($answer, ['y', 'yes'], true);
    }
}
