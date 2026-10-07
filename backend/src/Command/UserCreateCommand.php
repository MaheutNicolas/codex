<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Creates an account (the only way to register a user)')]
final class UserCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, '3 to 50 characters: letters, digits, dots, dashes and underscores');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string) $input->getArgument('username');

        if (1 !== preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $io->error('The username must have 3 to 50 characters: letters, digits, dots, dashes and underscores.');

            return Command::FAILURE;
        }
        if (null !== $this->users->findByUsername($username)) {
            $io->error(\sprintf('The username "%s" is already taken.', $username));

            return Command::FAILURE;
        }
        if (!$input->isInteractive()) {
            $io->error('The password is asked interactively: run this command in a terminal.');

            return Command::FAILURE;
        }

        $password = $io->askHidden('Password (8 characters minimum)');
        if (null === $password || mb_strlen($password) < 8) {
            $io->error('The password must have at least 8 characters.');

            return Command::FAILURE;
        }
        if ($password !== $io->askHidden('Confirm the password')) {
            $io->error('The two passwords differ.');

            return Command::FAILURE;
        }

        $user = (new User())->setUsername($username);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(\sprintf('Account "%s" created.', $username));

        return Command::SUCCESS;
    }
}
