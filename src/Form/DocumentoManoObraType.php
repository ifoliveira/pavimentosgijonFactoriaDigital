<?php
// src/Form/DocumentoManoObraType.php
namespace App\Form;

use App\Entity\Documento;
use App\Entity\ManoObra;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentoManoObraType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('manoObra', CollectionType::class, [
            'entry_type'    => ManoObraType::class,
            'allow_add'     => true,
            'allow_delete'  => true,
            'by_reference'  => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Documento::class]);
    }
}