<?php

namespace App\DataFixtures;

use App\Entity\AuthorBook;
use App\Entity\Authors;
use App\Entity\BookCategory;
use App\Entity\Books;
use App\Entity\Borrows;
use App\Entity\Categories;
use App\Entity\Settings;
use App\Entity\Users;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Demo data for local development and onboarding.
 *
 * Load: php bin/console doctrine:fixtures:load --no-interaction
 * (Wipes the database first — use only on dev/staging.)
 */
class AppFixtures extends Fixture
{
    private const CATEGORY_COUNT = 10;

    private const AUTHOR_COUNT = 50;

    private const BOOK_COUNT = 100;

    /** @var list<array{name: string, slug: string}> */
    private const CATEGORIES = [
        ['name' => 'Fiction', 'slug' => 'fiction'],
        ['name' => 'Literary fiction', 'slug' => 'literary-fiction'],
        ['name' => 'Mystery & thriller', 'slug' => 'mystery-thriller'],
        ['name' => 'Science fiction', 'slug' => 'science-fiction'],
        ['name' => 'Fantasy', 'slug' => 'fantasy'],
        ['name' => 'History', 'slug' => 'history'],
        ['name' => 'Biography & memoir', 'slug' => 'biography-memoir'],
        ['name' => 'Science & nature', 'slug' => 'science-nature'],
        ['name' => 'Poetry', 'slug' => 'poetry'],
        ['name' => 'Young adult', 'slug' => 'young-adult'],
    ];

    /** @var list<array{0: string, 1: string}> first name, last name */
    private const AUTHORS = [
        ['Elena', 'Vargas'], ['Marcus', 'Chen'], ['Sofia', 'Lindström'], ['James', "O'Brien"], ['Amara', 'Okonkwo'],
        ['Thomas', 'Mercer'], ['Yuki', 'Tanaka'], ['Isabelle', 'Moreau'], ['David', 'Kowalski'], ['Priya', 'Sharma'],
        ['Lucas', 'Ferreira'], ['Nadia', 'Petrova'], ['Oliver', 'Hughes'], ['Fatima', 'El-Masri'], ['Henrik', 'Berg'],
        ['Clara', 'Weiss'], ['Miguel', 'Santos'], ['Aisha', 'Diallo'], ['Daniel', 'Novák'], ['Rosa', 'García'],
        ['Viktor', 'Janković'], ['Mei', 'Lin'], ['Jonas', 'Eriksson'], ['Hannah', 'Schmidt'], ['Kwame', 'Asante'],
        ['Irina', 'Popescu'], ['Benjamin', 'Cohen'], ['Camille', 'Dubois'], ['Raj', 'Patel'], ['Eva', 'Horváth'],
        ['Stefan', 'Müller'], ['Layla', 'Haddad'], ['Antonio', 'Romano'], ['Grace', 'Okafor'], ['Felix', 'Bernard'],
        ['Nina', 'Krstić'], ['Ethan', 'Brooks'], ['Zara', 'Malik'], ['Pierre', 'Lefèvre'], ['Yelena', 'Volkov'],
        ['Samuel', 'Adjei'], ['Ingrid', 'Svensson'], ['Carlos', 'Rivera'], ['Mira', 'Kaur'], ['Theo', 'Van Dijk'],
        ['Leila', 'Farah'], ['Anders', 'Holm'], ['Julia', 'Costa'], ['Kenji', 'Mori'], ['Anna', 'Nowak'], ['Dmitri', 'Sokolov'],
    ];

    /** @var list<string> */
    private const BOOK_TITLES = [
        '1984', 'Animal Farm', 'Pride and Prejudice', 'Jane Eyre', 'Wuthering Heights',
        'Great Expectations', 'Oliver Twist', 'Moby-Dick', 'The Scarlet Letter', 'Walden',
        'Little Women', 'Anna Karenina', 'Crime and Punishment', 'War and Peace', 'The Brothers Karamazov',
        'One Hundred Years of Solitude', 'Love in the Time of Cholera', 'The House of the Spirits', 'The Alchemist', 'Blindness',
        'Norwegian Wood', 'Kafka on the Shore', 'Never Let Me Go', 'The Remains of the Day', 'Atonement',
        'The Goldfinch', 'The Secret History', 'The Shadow of the Wind', 'The Name of the Wind', 'Dune',
        'Neuromancer', 'Foundation', 'Brave New World', 'Fahrenheit 451', 'The Handmaid\'s Tale',
        'Beloved', 'Invisible Man', 'Their Eyes Were Watching God', 'Things Fall Apart', 'Half of a Yellow Sun',
        'Americanah', 'Educated', 'The Immortal Life of Henrietta Lacks', 'Sapiens', 'Homo Deus',
        'Guns, Germs, and Steel', 'The Sixth Extinction', 'A Brief History of Time', 'Silent Spring', 'The Gene',
        'Cosmos', 'The Selfish Gene', 'Thinking, Fast and Slow', 'Outliers', 'The Body Keeps the Score',
        'Kitchen Confidential', 'Salt', 'The Splendid and the Vile', 'SPQR', 'The Warmth of Other Suns',
        'Between the World and Me', 'The Fire Next Time', 'Giovanni\'s Room', 'The Picture of Dorian Gray', 'Dracula',
        'Frankenstein', 'Strange Case of Dr Jekyll and Mr Hyde', 'The Time Machine', 'Twenty Thousand Leagues Under the Sea', 'Les Misérables',
        'The Count of Monte Cristo', 'The Three Musketeers', 'The Little Prince', 'Candide', 'The Stranger',
        'The Plague', 'The Myth of Sisyphus', 'Siddhartha', 'Steppenwolf', 'The Metamorphosis',
        'Demon Copperhead', 'Pachinko', 'The Vegetarian', 'Normal People', 'Conversations with Friends',
        'Circe', 'The Song of Achilles', 'The Nightingale', 'All the Light We Cannot See', 'The Book Thief',
        'Life of Pi', 'The Kite Runner', 'A Thousand Splendid Suns', 'Cutting for Stone', 'The God of Small Things',
        'Midnight\'s Children', 'Shantaram', 'The Power of Now', 'Man\'s Search for Meaning', 'Meditations',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SluggerInterface $slugger,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $admin = (new Users())
            ->setFirstName('Admin')
            ->setLastName('User')
            ->setEmail('admin@admin.com')
            ->setRole(Users::ROLE_ADMIN)
            ->setBorrowLimit(10);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin12345'));

        $member = (new Users())
            ->setFirstName('Jane')
            ->setLastName('Member')
            ->setEmail('member@example.com')
            ->setRole(Users::ROLE_MEMBER)
            ->setBorrowLimit(3);
        $member->setPassword($this->passwordHasher->hashPassword($member, 'Member12345'));

        $manager->persist($admin);
        $manager->persist($member);
        $manager->flush();

        $categories = [];
        for ($i = 0; $i < self::CATEGORY_COUNT; ++$i) {
            $def = self::CATEGORIES[$i];
            $cat = (new Categories())
                ->setName($def['name'])
                ->setSlug($def['slug']);
            $manager->persist($cat);
            $categories[] = $cat;
        }

        $authors = [];
        for ($i = 0; $i < self::AUTHOR_COUNT; ++$i) {
            [$first, $last] = self::AUTHORS[$i];
            $author = (new Authors())
                ->setFirstName($first)
                ->setLastName($last);
            $manager->persist($author);
            $authors[] = $author;
        }

        $books = [];
        for ($i = 0; $i < self::BOOK_COUNT; ++$i) {
            $title = self::BOOK_TITLES[$i];
            $slug  = $this->slugger->slug($title)->lower()->toString() . '-' . $i;
            $book  = (new Books())->setTitle($title)->setSlug($slug);

            if ($i % 4 === 0) {
                $book->setBorrowDaysLimit([7, 14, 21, 28][(int) ($i / 4) % 4]);
            } else {
                $book->setBorrowDaysLimit(null);
            }

            if ($i % 8 === 0) {
                $book->setCopiesTotal(2 + ($i % 3));
            } else {
                $book->setCopiesTotal(1);
            }

            $manager->persist($book);
            $books[] = $book;
        }

        $manager->flush();

        foreach ($books as $i => $book) {
            $primaryAuthor = $authors[$i % self::AUTHOR_COUNT];
            $manager->persist((new AuthorBook())->setBook($book)->setAuthor($primaryAuthor));

            if ($i % 5 === 0) {
                $second = $authors[($i + 17) % self::AUTHOR_COUNT];
                if ($second !== $primaryAuthor) {
                    $manager->persist((new AuthorBook())->setBook($book)->setAuthor($second));
                }
            }

            $primaryCat = $categories[$i % self::CATEGORY_COUNT];
            $manager->persist((new BookCategory())->setBook($book)->setCategory($primaryCat));

            if ($i % 6 === 0) {
                $secondCat = $categories[($i + 3) % self::CATEGORY_COUNT];
                if ($secondCat !== $primaryCat) {
                    $manager->persist((new BookCategory())->setBook($book)->setCategory($secondCat));
                }
            }
        }

        $defaultLimit = (new Settings())
            ->setKey('default_borrow_limit')
            ->setValue('5')
            ->setUpdatedBy($admin);
        $defaultDays = (new Settings())
            ->setKey('default_borrow_days')
            ->setValue('14')
            ->setUpdatedBy($admin);
        $manager->persist($defaultLimit);
        $manager->persist($defaultDays);

        for ($b = 0; $b < 5; ++$b) {
            $borrow = (new Borrows())
                ->setBook($books[$b])
                ->setMember($member)
                ->setBorrowedAt(new \DateTimeImmutable(sprintf('-%d days', 2 + $b)))
                ->setDueDate(new \DateTimeImmutable(sprintf('+%d days', 10 + $b)))
                ->setReturnedAt(null);
            $manager->persist($borrow);
        }

        for ($k = 0; $k < 15; ++$k) {
            $bookIndex = 10 + (($k * 7) % 90);
            $borrowed = new \DateTimeImmutable(sprintf('-%d days', 80 + $k * 3));
            $due      = (clone $borrowed)->modify(sprintf('+%d days', 14));
            $returned = (clone $borrowed)->modify(sprintf('+%d days', 5 + ($k % 10)));

            $borrow = (new Borrows())
                ->setBook($books[$bookIndex])
                ->setMember($member)
                ->setBorrowedAt($borrowed)
                ->setDueDate($due)
                ->setReturnedAt($returned);
            $manager->persist($borrow);
        }

        $manager->flush();
    }
}
