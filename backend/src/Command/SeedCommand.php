<?php

namespace App\Command;

use App\Entity\Book;
use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\Knowledge;
use App\Repository\BookRepository;
use App\Repository\EventParticipantRepository;
use App\Repository\EventRepository;
use App\Repository\KnowledgeRepository;
use App\Repository\UserRepository;
use App\Validation\Validate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed', description: 'Loads an example book (Aldric, Mira, the Northern Citadel and two events) into an account')]
final class SeedCommand extends Command
{
    private const BOOK_NAME = 'Example Book';

    private const KNOWLEDGE = [
        [
            'slug' => 'aldric',
            'type' => 'character',
            'name' => 'Aldric',
            'summary' => 'One-eyed knight sworn to the Northern Citadel.',
            'description' => 'Aldric lost his left eye defending the Northern Citadel. He swore an oath of loyalty to Mira and the council, and was later betrayed by them.',
            'aliases' => ['the One-Eyed'],
        ],
        [
            'slug' => 'mira',
            'type' => 'character',
            'name' => 'Mira',
            'summary' => 'Council advisor and Aldric\'s ally, until the betrayal.',
            'description' => null,
            'aliases' => [],
        ],
        [
            'slug' => 'citadelle-nord',
            'type' => 'place',
            'name' => 'Northern Citadel',
            'summary' => 'Fortress guarding the northern border, seat of the council.',
            'description' => null,
            'aliases' => ['the Citadel'],
        ],
    ];

    private const EVENTS = [
        [
            'slug' => 'evt-0041',
            'title' => 'The Oath',
            'summary' => 'Aldric swears loyalty to the council in the Northern Citadel.',
            'worldOrder' => 41,
            'worldDate' => 'Year 312, winter',
            'chapter' => 3,
        ],
        [
            'slug' => 'evt-0042',
            'title' => 'The Council\'s Betrayal',
            'summary' => 'Mira turns the council against Aldric.',
            'worldOrder' => 42,
            'worldDate' => 'Year 312, spring',
            'chapter' => 7,
        ],
    ];

    /** [event slug, knowledge slug, role] */
    private const PARTICIPANTS = [
        ['evt-0041', 'aldric', 'author'],
        ['evt-0041', 'mira', 'witness'],
        ['evt-0041', 'citadelle-nord', 'place'],
        ['evt-0042', 'mira', 'author'],
        ['evt-0042', 'aldric', 'victim'],
        ['evt-0042', 'citadelle-nord', 'place'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly BookRepository $books,
        private readonly KnowledgeRepository $knowledgeRepository,
        private readonly EventRepository $eventRepository,
        private readonly EventParticipantRepository $participantRepository,
        private readonly Validate $validate,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The account that will own the example book')
            ->addOption('reset', null, InputOption::VALUE_NONE, sprintf('Deletes the "%s" of this account, with all its content, before loading', self::BOOK_NAME));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string) $input->getArgument('username');

        $user = $this->users->findByUsername($username);
        if (null === $user) {
            $io->error(\sprintf('No account named "%s". Create it first with app:user:create.', $username));

            return Command::FAILURE;
        }

        $book = $this->books->findOneBy(['user' => $user, 'name' => self::BOOK_NAME]);

        if ($input->getOption('reset') && null !== $book) {
            if (!$io->confirm(\sprintf('This deletes the book "%s" of "%s" with all its content. Continue?', self::BOOK_NAME, $username), false)) {
                $io->warning('Aborted, nothing was deleted.');

                return Command::FAILURE;
            }
            // The content of the book is removed by the database (ON DELETE CASCADE).
            $this->em->remove($book);
            $this->em->flush();
            $this->em->clear();
            $user = $this->users->findByUsername($username);
            $book = null;
            $io->note('Existing example book deleted.');
        }

        $created = $skipped = 0;

        if (null === $book) {
            $book = (new Book())->setUser($user)->setName(self::BOOK_NAME);
            $this->em->persist($book);
            ++$created;
        } else {
            ++$skipped;
        }

        $knowledge = [];
        foreach (self::KNOWLEDGE as $row) {
            $entry = null === $book->getId() ? null : $this->knowledgeRepository->findBySlug($book, $row['slug']);
            if (null !== $entry) {
                $knowledge[$row['slug']] = $entry;
                ++$skipped;
                continue;
            }

            $entry = (new Knowledge())
                ->setBook($book)
                ->setSlug($row['slug'])
                ->setType($row['type'])
                ->setName($row['name'])
                ->setSummary($row['summary'])
                ->setDescription($row['description'])
                ->setAliases($row['aliases']);
            $this->validate->entity($entry);
            $this->em->persist($entry);
            $knowledge[$row['slug']] = $entry;
            ++$created;
        }

        $events = [];
        foreach (self::EVENTS as $row) {
            $event = null === $book->getId() ? null : $this->eventRepository->findBySlug($book, $row['slug']);
            if (null !== $event) {
                $events[$row['slug']] = $event;
                ++$skipped;
                continue;
            }

            $event = (new Event())
                ->setBook($book)
                ->setSlug($row['slug'])
                ->setTitle($row['title'])
                ->setSummary($row['summary'])
                ->setWorldOrder($row['worldOrder'])
                ->setWorldDate($row['worldDate'])
                ->setChapter($row['chapter']);
            $this->validate->entity($event);
            $this->em->persist($event);
            $events[$row['slug']] = $event;
            ++$created;
        }

        foreach (self::PARTICIPANTS as [$eventSlug, $knowledgeSlug, $role]) {
            if (null !== $book->getId() && null !== $this->participantRepository->findLink($book, $eventSlug, $knowledgeSlug)) {
                ++$skipped;
                continue;
            }

            $this->em->persist(
                (new EventParticipant())
                    ->setEvent($events[$eventSlug])
                    ->setKnowledge($knowledge[$knowledgeSlug])
                    ->setRole($role),
            );
            ++$created;
        }

        $this->em->flush();

        $io->success(\sprintf('Book "%s" (id %d): %d record(s) created, %d already present.', self::BOOK_NAME, $book->getId(), $created, $skipped));

        return Command::SUCCESS;
    }
}
