<?php

namespace App\Form\Admin;

use App\Entity\Authors;
use App\Entity\Books;
use App\Entity\Categories;
use App\Repository\AuthorBookRepository;
use App\Repository\BookCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;

final class BookAdminFormType extends AbstractType
{
    public function __construct(
        private readonly AuthorBookRepository $authorBookRepository,
        private readonly BookCategoryRepository $bookCategoryRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Title'])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'help'  => 'Unique URL segment (e.g. nineteen-eighty-four-0).',
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 6],
                'help'     => 'Shown on the public book detail page only (not in the catalog list).',
                'constraints' => [
                    new Length([
                        'max'        => 10000,
                        'maxMessage' => 'Description cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('borrowDaysLimit', IntegerType::class, [
                'label'    => 'Borrow days limit',
                'required' => false,
                'help'     => 'Leave empty to use the global default.',
            ])
            ->add('authors', EntityType::class, [
                'label'         => 'Authors',
                'class'         => Authors::class,
                'choice_label'  => fn (Authors $a) => $a->getLastName().', '.$a->getFirstName(),
                // String values must match expanded checkbox values (strict in_array in CheckboxListMapper).
                'choice_value'  => static fn (Authors $a): string => (string) $a->getId(),
                'multiple'      => true,
                'expanded'      => true,
                'mapped'        => false,
                'required'      => false,
                'attr'          => ['class' => 'admin-checkbox-list'],
                'help'          => 'Select at least one author. Scroll the list if there are many.',
                'constraints'   => [
                    new Count([
                        'min'        => 1,
                        'minMessage' => 'Select at least one author.',
                    ]),
                ],
            ])
            ->add('categories', EntityType::class, [
                'label'         => 'Categories',
                'class'         => Categories::class,
                'choice_label'  => 'name',
                'choice_value'  => static fn (Categories $c): string => (string) $c->getId(),
                'multiple'      => true,
                'expanded'      => true,
                'mapped'        => false,
                'required'      => false,
                'attr'          => ['class' => 'admin-checkbox-list'],
                'help'          => 'Select at least one category.',
                'constraints'   => [
                    new Count([
                        'min'        => 1,
                        'minMessage' => 'Select at least one category.',
                    ]),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save',
                'attr'  => ['class' => 'admin-btn admin-btn-primary'],
            ]);

        // After the book is bound, hydrate unmapped checkbox lists from junction tables.
        // Resolve selected models via the field choice_list so values match expanded checkboxes (strict types).
        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event): void {
                $book = $event->getData();
                if (!$book instanceof Books || !$book->getId()) {
                    return;
                }

                $form = $event->getForm();
                $bid  = (int) $book->getId();

                $authorIds = $this->authorBookRepository->createQueryBuilder('ab')
                    ->select('IDENTITY(ab.author)')
                    ->andWhere('IDENTITY(ab.book) = :bid')
                    ->setParameter('bid', $bid)
                    ->getQuery()
                    ->getSingleColumnResult();

                $authorValues = array_values(array_unique(array_map(
                    static fn (mixed $id): string => (string) (int) $id,
                    $authorIds,
                )));

                $authorsForm       = $form->get('authors');
                $authorsChoiceList = $authorsForm->getConfig()->getAttribute('choice_list');
                if (null !== $authorsChoiceList) {
                    $authorsForm->setData(
                        $authorValues === []
                            ? []
                            : array_values($authorsChoiceList->getChoicesForValues($authorValues)),
                    );
                }

                $categoryIds = $this->bookCategoryRepository->createQueryBuilder('bc')
                    ->select('IDENTITY(bc.category)')
                    ->andWhere('IDENTITY(bc.book) = :bid')
                    ->setParameter('bid', $bid)
                    ->getQuery()
                    ->getSingleColumnResult();

                $categoryValues = array_values(array_unique(array_map(
                    static fn (mixed $id): string => (string) (int) $id,
                    $categoryIds,
                )));

                $categoriesForm       = $form->get('categories');
                $categoriesChoiceList = $categoriesForm->getConfig()->getAttribute('choice_list');
                if (null !== $categoriesChoiceList) {
                    $categoriesForm->setData(
                        $categoryValues === []
                            ? []
                            : array_values($categoriesChoiceList->getChoicesForValues($categoryValues)),
                    );
                }
            },
            -255,
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Books::class,
        ]);
    }
}
