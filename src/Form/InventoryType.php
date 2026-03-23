<?php

namespace App\Form;

use App\Entity\Inventory;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class InventoryType extends AbstractType
{
    public function __construct(
        private StockRepository $stockRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        $inventory = $options['data'] ?? null;
        $product = $inventory && $inventory->getProduct() ? $inventory->getProduct() : null;

        $builder
            ->add('product', EntityType::class, [
                'label' => 'Product',
                'class' => Product::class,
                'choice_label' => 'Name',
                'placeholder' => 'Select a product',
                'required' => true,
                'data' => $product,
                'attr' => [
                    'class' => 'form-control',
                ],
                'query_builder' => function (ProductRepository $productRepository) {
                    // Only show products that exist in Stock (flower products)
                    // Join with Stock table to get only products that have stock entries
                    return $productRepository->createQueryBuilder('p')
                        ->innerJoin('p.stocks', 's')
                        ->groupBy('p.id')
                        ->orderBy('p.Name', 'ASC');
                },
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select a product',
                    ]),
                ],
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'Quantity',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter quantity',
                    'min' => '1',
                    'step' => '1',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a quantity',
                    ]),
                    new Positive([
                        'message' => 'Quantity must be a positive number',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Inventory::class,
            'is_edit' => false,
        ]);
    }
}

