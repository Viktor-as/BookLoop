<?php

namespace App\Controller\Admin;

use App\Entity\Settings;
use App\Entity\Users;
use App\Form\Admin\SettingFormType;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
final class AdminSettingController extends AbstractController
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_settings_index', methods: ['GET'])]
    public function index(): Response
    {
        $settings = $this->settingsRepository->findBy(
            [],
            ['updatedAt' => 'DESC', 'key' => 'ASC'],
        );

        return $this->render('admin/settings/index.html.twig', ['settings' => $settings]);
    }

    #[Route('/new', name: 'admin_settings_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $setting = new Settings();
        $form    = $this->createForm(SettingFormType::class, $setting, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if ($user instanceof Users) {
                $setting->setUpdatedBy($user);
            }
            $this->em->persist($setting);
            $this->em->flush();
            $this->addFlash('success', 'Setting created.');

            return $this->redirectToRoute('admin_settings_index');
        }

        return $this->render('admin/settings/form.html.twig', [
            'form'  => $form,
            'title' => 'New setting',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_settings_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Settings $setting): Response
    {
        $form = $this->createForm(SettingFormType::class, $setting, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if ($user instanceof Users) {
                $setting->setUpdatedBy($user);
            }
            $this->em->flush();
            $this->addFlash('success', 'Setting updated.');

            return $this->redirectToRoute('admin_settings_index');
        }

        return $this->render('admin/settings/form.html.twig', [
            'form'  => $form,
            'title' => 'Edit setting',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_settings_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Settings $setting): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_setting_'.$setting->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->em->remove($setting);
        $this->em->flush();
        $this->addFlash('success', 'Setting deleted.');

        return $this->redirectToRoute('admin_settings_index');
    }
}
