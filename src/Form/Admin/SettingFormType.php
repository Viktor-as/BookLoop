<?php

namespace App\Form\Admin;

use App\Entity\Settings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SettingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('key', TextType::class, [
                'label'    => 'Key',
                'disabled' => $isEdit,
                'attr'     => $isEdit ? ['readonly' => true] : [],
            ])
            ->add('value', TextareaType::class, ['label' => 'Value'])
            ->add('save', SubmitType::class, [
                'label' => 'Save',
                'attr'  => ['class' => 'admin-btn admin-btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Settings::class,
            'is_edit'    => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
