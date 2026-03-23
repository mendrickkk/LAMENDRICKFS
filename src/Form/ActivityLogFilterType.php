<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityLogFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', TextType::class, [
                'required' => false,
                'label' => 'User (ID or Username)',
                'attr' => ['placeholder' => 'Search by User'],
            ])
            ->add('action', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'Login' => 'LOGIN',
                    'Logout' => 'LOGOUT',
                    'Create' => 'CREATE',
                    'Update' => 'UPDATE',
                    'Delete' => 'DELETE',
                ],
                'placeholder' => 'All Actions',
            ])
            ->add('targetEntity', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'Product' => 'Product',
                    'Stock' => 'Stock',
                    'Category' => 'Category',
                    'Inventory' => 'Inventory',
                    'User' => 'Users',
                ],
                'placeholder' => 'All Entities',
            ])
            ->add('dateFrom', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'label' => 'From Date',
            ])
            ->add('dateTo', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'label' => 'To Date',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}

