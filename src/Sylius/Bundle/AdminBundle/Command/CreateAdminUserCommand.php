<?php

declare(strict_types=1);

namespace Sylius\Bundle\AdminBundle\Command;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\User\Canonicalizer\CanonicalizerInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Intl\Locales;

#[AsCommand(
    name: 'sylius:admin-user:create',
    description: 'Create a new admin user'
)]
final class CreateAdminUserCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private UserRepositoryInterface $adminUserRepository,
        private FactoryInterface $adminUserFactory,
        private CanonicalizerInterface $canonicalizer,
    ) {
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->isInteractive()) {
            $this->io->error('This command must be run interactively.');

            return Command::FAILURE;
        }

        $this->io->title('Admin user creation');

        $email = $this->io->askQuestion($this->createEmailQuestion());

        if ($this->checkIfAdminUserExists($this->canonicalizer->canonicalize($email))) {
            $this->io->error(sprintf('Admin user with email address %s already exists.', $email));

            return Command::INVALID;
        }

        $userName = $this->io->ask('Username');
        $firstName = $this->io->ask('First name');
        $lastName = $this->io->ask('Last name');
        $password = $this->io->askHidden('Password');

        /** @var AdminUserInterface $adminUser */
        $adminUser = $this->adminUserFactory->createNew();

        $adminUser->setEmail($email);
        $adminUser->setPlainPassword($password);
        $adminUser->setUsername($userName);
        $adminUser->setFirstName($firstName);
        $adminUser->setLastName($lastName);

        $locales = Locales::getNames();

        $localeCode = $this->io->choice('Select the locale code', $locales, 'en_US');
        $adminUser->setLocaleCode($localeCode);

        $enabled = $this->io->confirm('Do you want to enable this admin user?', true);
        $adminUser->setEnabled($enabled);

        $this->adminUserRepository->add($adminUser);

        $this->io->success(sprintf('Admin user %s was successfully created', $adminUser->getEmail()));

        return Command::SUCCESS;
    }

    private function createEmailQuestion(): Question
    {
        $question = new Question('Email');
        $question->setValidator(function (?string $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $email === null) {
                throw new \InvalidArgumentException('The e-mail address provided is invalid. Please try again.');
            }

            return $email;
        });
        $question->setMaxAttempts(3);

        return $question;
    }

    private function checkIfAdminUserExists(string $email): bool
    {
        return null !== $this->adminUserRepository->findOneByEmail($email);
    }
}
