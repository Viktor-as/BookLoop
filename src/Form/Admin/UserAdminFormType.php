<?php

namespace App\Form\Admin;

use App\Entity\Users;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UserAdminFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $requirePassword = $options['require_password'];

        $builder
            ->add('firstName', TextType::class, ['label' => 'First name'])
            ->add('lastName', TextType::class, ['label' => 'Last name'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('role', ChoiceType::class, [
                'label'   => 'Role',
                'choices' => [
                    'Member' => Users::ROLE_MEMBER,
                    'Admin'  => Users::ROLE_ADMIN,
                ],
            ])
            ->add('borrowLimit', IntegerType::class, [
                'label'    => 'Borrow limit',
                'required' => false,
                'attr'     => ['placeholder' => 'Use default if empty'],
            ]);

        $firstPwdOpts  = ['label' => 'Password'];
        $secondPwdOpts = ['label' => 'Repeat password'];
        if ($requirePassword) {
            $pwdConstraints = [
                new NotBlank(message: 'Password is required for new users.'),
                new Length(min: 8, max: 4096),
            ];
            $firstPwdOpts['constraints']  = $pwdConstraints;
            $secondPwdOpts['constraints'] = $pwdConstraints;
        }

        $builder->add('plainPassword', RepeatedType::class, [
            'type'            => PasswordType::class,
            'mapped'          => false,
            'required'        => $requirePassword,
            'first_options'   => $firstPwdOpts,
            'second_options'  => $secondPwdOpts,
            'invalid_message' => 'Password fields must match.',
        ]);

        $builder->add('save', SubmitType::class, [
            'label' => 'Save',
            'attr'  => ['class' => 'admin-btn admin-btn-primary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Users::class,
            'require_password' => true,
        ]);
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
