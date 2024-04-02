<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;

class pdfUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('pdfFile', FileType::class, [
                'label' => 'Upload PDF',
                'mapped' => false, // no se asocia directamente con una propiedad de entidad
            ]);
    }
}

?>