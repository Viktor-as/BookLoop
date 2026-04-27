<?php

namespace App\Controller\Admin;

use App\Entity\AuthorBook;
use App\Entity\BookCategory;
use App\Entity\Settings;
use App\Entity\Users;
use App\Form\Admin\UserAdminFormType;
use App\Repository\BorrowsRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly BorrowsRepository $borrowsRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('', name: 'admin_users_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page    = max(1, $request->query->getInt('page', 1));
        $perPage = 10;

        $qb = $this->usersRepository->createQueryBuilder('u')
            ->orderBy('u.updatedAt', 'DESC')
            ->addOrderBy('u.email', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator   = new Paginator($qb);
        $totalItems  = $paginator->count();
        $maxPage     = max(1, (int) ceil($totalItems / $perPage));
        if ($page > $maxPage) {
            return $this->redirectToRoute('admin_users_index', ['page' => $maxPage]);
        }

        $users = iterator_to_array($paginator);

        return $this->render('admin/users/index.html.twig', [
            'users'      => $users,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalItems' => $totalItems,
        ]);
    }

    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new Users();
        $form = $this->createForm(UserAdminFormType::class, $user, ['require_password' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
            $this->em->persist($user);
            $this->em->flush();
            $this->addFlash('success', 'User created.');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/form.html.twig', [
            'form'  => $form,
            'title' => 'New user',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Users $user): Response
    {
        $current = $this->getUser();
        if (!$current instanceof Users) {
            throw $this->createAccessDeniedException();
        }

        $originalRole = $user->getRole();
        $form         = $this->createForm(UserAdminFormType::class, $user, ['require_password' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($originalRole === Users::ROLE_ADMIN
                && $user->getRole() === Users::ROLE_MEMBER
                && $this->countOtherAdmins($user->getId()) === 0
            ) {
                $this->addFlash('error', 'You cannot demote the only administrator.');

                return $this->redirectToRoute('admin_users_edit', ['id' => $user->getId()]);
            }

            $plain = (string) $form->get('plainPassword')->getData();
            if ($plain !== '') {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
            }

            $this->em->flush();
            $this->addFlash('success', 'User updated.');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/form.html.twig', [
            'form'  => $form,
            'title' => 'Edit user',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_users_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Users $user): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_user_'.$user->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $current = $this->getUser();
        if ($current instanceof Users && $current->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');

            return $this->redirectToRoute('admin_users_index');
        }

        if ($user->getRole() === Users::ROLE_ADMIN && $this->usersRepository->countByRole(Users::ROLE_ADMIN) <= 1) {
            $this->addFlash('error', 'You cannot delete the only administrator.');

            return $this->redirectToRoute('admin_users_index');
        }

        if ($this->borrowsRepository->countBorrowsForMember((int) $user->getId()) > 0) {
            $this->addFlash('error', 'Cannot delete a user who has borrow records.');

            return $this->redirectToRoute('admin_users_index');
        }

        foreach ($this->em->getRepository(Settings::class)->findBy(['updatedBy' => $user]) as $s) {
            $s->setUpdatedBy(null);
        }
        foreach ($this->em->getRepository(AuthorBook::class)->findBy(['updatedBy' => $user]) as $ab) {
            $ab->setUpdatedBy(null);
        }
        foreach ($this->em->getRepository(BookCategory::class)->findBy(['updatedBy' => $user]) as $bc) {
            $bc->setUpdatedBy(null);
        }
        $this->em->flush();

        $this->em->remove($user);
        $this->em->flush();
        $this->addFlash('success', 'User deleted.');

        return $this->redirectToRoute('admin_users_index');
    }

    private function countOtherAdmins(int $excludeUserId): int
    {
        return (int) $this->usersRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.role = :admin')
            ->andWhere('u.id != :id')
            ->setParameter('admin', Users::ROLE_ADMIN)
            ->setParameter('id', $excludeUserId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
