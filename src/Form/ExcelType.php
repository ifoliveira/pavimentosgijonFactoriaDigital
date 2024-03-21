<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExcelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('excel_file', FileType::class, [
                'label' => 'Seleccione el archivo Excel (.xlsx)',
                'mapped' => false, // 'mapped' => false significa que este campo no está asociado a ninguna propiedad de una entidad
                'required' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Subir archivo'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configura tus opciones predeterminadas aquí si es necesario
        ]);
    }
}
