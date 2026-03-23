<?php

namespace App\Form;

use App\Entity\Stock;
use App\Entity\Product;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        $stock = $options['data'] ?? null;
        $product = $stock && $stock->getProduct() ? $stock->getProduct() : null;
        
        // Add quantity field (applies to both create and edit)
        $builder
            ->add('quantity', NumberType::class, [
                'label' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Quantity is required.']),
                    new Positive(['message' => 'Quantity must be a positive number.']),
                ],
                'attr' => [
                    'placeholder' => 'Enter quantity',
                    'min' => '1',
                    'step' => '1',
                ],
            ]);
        
        if ($isEdit && $product) {
            // For editing: show existing product selection and editable product fields
            $builder
                ->add('existingProduct', EntityType::class, [
                    'label' => false,
                    'class' => Product::class,
                    'choice_label' => 'Name',
                    'placeholder' => 'Select existing product (or fill form below to update product)',
                    'mapped' => false,
                    'required' => false,
                    'data' => $product,
                    'attr' => [
                        'class' => 'existing-product-select',
                    ],
                    'query_builder' => function (\App\Repository\ProductRepository $productRepository) {
                        return $productRepository->createQueryBuilder('p')
                            ->orderBy('p.Name', 'ASC');
                    },
                ])
                ->add('productName', TextType::class, [
                    'label' => false,
                    'mapped' => false,
                    'required' => false,
                    'data' => $product->getName(),
                    'attr' => [
                        'placeholder' => 'Enter product name',
                    ],
                ])
                ->add('productDescription', TextareaType::class, [
                    'label' => false,
                    'mapped' => false,
                    'required' => false,
                    'data' => $product->getDescription(),
                    'attr' => [
                        'placeholder' => 'Enter product description',
                        'rows' => 4,
                    ],
                ])
                ->add('productPrice', NumberType::class, [
                    'label' => false,
                    'mapped' => false,
                    'required' => false,
                    'data' => $product->getPrice(),
                    'attr' => [
                        'placeholder' => '0.00',
                        'step' => '0.01',
                        'min' => '0',
                    ],
                ])
                ->add('productCategory', EntityType::class, [
                    'label' => false,
                    'class' => Category::class,
                    'choice_label' => 'name',
                    'placeholder' => 'Select a category',
                    'mapped' => false,
                    'required' => false,
                    'data' => $product->getCategory(),
                    'query_builder' => function (CategoryRepository $categoryRepository) {
                        return $categoryRepository->createQueryBuilder('c')
                            ->where('c.isActive = :active')
                            ->setParameter('active', true)
                            ->orderBy('c.name', 'ASC');
                    },
                ])
                ->add('productImage', FileType::class, [
                    'label' => false,
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'accept' => 'image/*',
                        'class' => 'image-upload-input',
                    ],
                ]);
        } else {
            // For creating: show existing product selector (optional) and new product fields
            $builder
                ->add('existingProduct', EntityType::class, [
                    'label' => false,
                    'class' => Product::class,
                    'choice_label' => 'Name',
                    'placeholder' => 'Select existing product (or fill form below to create new)',
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'class' => 'existing-product-select',
                    ],
                    'query_builder' => function (\App\Repository\ProductRepository $productRepository) {
                        return $productRepository->createQueryBuilder('p')
                            ->orderBy('p.Name', 'ASC');
                    },
                ])
                ->add('productName', TextType::class, [
                    'label' => false,
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Enter product name',
                    ],
                ])
                ->add('productDescription', TextareaType::class, [
                    'label' => false,
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Enter product description',
                        'rows' => 4,
                    ],
                ])
                ->add('productPrice', NumberType::class, [
                    'label' => false,
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => '0.00',
                        'step' => '0.01',
                        'min' => '0',
                    ],
                ])
                ->add('productCategory', EntityType::class, [
                    'label' => false,
                    'class' => Category::class,
                    'choice_label' => 'name',
                    'placeholder' => 'Select a category',
                    'mapped' => false,
                    'required' => false,
                    'query_builder' => function (CategoryRepository $categoryRepository) {
                        return $categoryRepository->createQueryBuilder('c')
                            ->where('c.isActive = :active')
                            ->setParameter('active', true)
                            ->orderBy('c.name', 'ASC');
                    },
                ])
                ->add('productImage', FileType::class, [
                    'label' => false,
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'accept' => 'image/*',
                        'class' => 'image-upload-input',
                    ],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stock::class,
            'is_edit' => false,
        ]);
    }
}

