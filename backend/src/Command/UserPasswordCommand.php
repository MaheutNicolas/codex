<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:password', description: 'Changes the password of an account')]
final class UserPasswordCommand extends Command
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
        $this->addArgument('username', InputArgument::REQUIRED, 'The account to update');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string) $input->getArgument('username');

        $user = $this->users->findByUsername($username);
        if (null === $user) {
            $io->error(\sprintf('No account named "%s".', $username));

            return Command::FAILURE;
        }
        if (!$input->isInteractive()) {
            $io->error('The password is asked interactively: run this command in a terminal.');

            return Command::FAILURE;
        }

        $password = $io->askHidden('New password (8 characters minimum)');
        if (null === $password || mb_strlen($password) < 8) {
            $io->error('The password must have at least 8 characters.');

            return Command::FAILURE;
        }
        if ($password !== $io->askHidden('Confirm the password')) {
            $io->error('The two passwords differ.');

            return Command::FAILURE;
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->flush();

        $io->success(\sprintf('Password of "%s" updated.', $username));

        return Command::SUCCESS;
    }
}
