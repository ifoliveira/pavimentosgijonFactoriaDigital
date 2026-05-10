<?php

namespace App\Form;

use App\Entity\CatalogoProducto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CatalogoProductoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('codigo', TextType::class, [
                'required' => false,
            ])
            ->add('nombre', TextType::class)
            ->add('descripcion', TextareaType::class, [
                'required' => false,
            ])
            ->add('familia', ChoiceType::class, [
                'choices' => [
                    'Plato de ducha' => 'plato_ducha',
                    'Mampara' => 'mampara',
                    'Grifería' => 'griferia',
                    'Mueble baño' => 'mueble_bano',
                    'Sanitario' => 'sanitario',
                    'Azulejo' => 'azulejo',
                    'Pavimento' => 'pavimento',
                    'Material agarre' => 'material_agarre',
                    'Auxiliar' => 'auxiliar',
                ],
                'placeholder' => 'Selecciona familia',
            ])
            ->add('subfamilia', TextType::class, [
                'required' => false,
            ])
            ->add('marca', TextType::class, [
                'required' => false,
            ])
            ->add('modelo', TextType::class, [
                'required' => false,
            ])
            ->add('unidad', ChoiceType::class, [
                'choices' => [
                    'Unidad' => 'ud',
                    'Metro cuadrado' => 'm2',
                    'Metro lineal' => 'ml',
                    'Caja' => 'caja',
                    'Saco' => 'saco',
                ],
            ])
            ->add('precioVenta', MoneyType::class, [
                'currency' => 'EUR',
                'required' => true,
            ])
            ->add('precioCoste', MoneyType::class, [
                'currency' => 'EUR',
                'required' => true,
            ])
            ->add('tipoIva', ChoiceType::class, [
                'choices' => [
                    '21%' => '21',
                    '10%' => '10',
                    '0%' => '0',
                ],
            ])
            ->add('ancho', NumberType::class, [
                'required' => false,
                'label' => 'Ancho cm',
            ])
            ->add('alto', NumberType::class, [
                'required' => false,
                'label' => 'Alto cm',
            ])
            ->add('largo', NumberType::class, [
                'required' => false,
                'label' => 'Largo cm',
            ])
            ->add('fondo', NumberType::class, [
                'required' => false,
                'label' => 'Fondo cm',
            ])
            ->add('medidaTexto', TextType::class, [
                'required' => false,
                'label' => 'Medida texto',
            ])
            ->add('controlaStock', CheckboxType::class, [
                'required' => false,
            ])
            ->add('stockActual', NumberType::class, [
                'required' => false,
            ])
            ->add('stockMinimo', NumberType::class, [
                'required' => false,
            ])
            ->add('activo', CheckboxType::class, [
                'required' => false,
            ])
            ->add('visiblePresupuesto', CheckboxType::class, [
                'required' => false,
                'label' => 'Visible en presupuestos',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CatalogoProducto::class,
        ]);
    }
}