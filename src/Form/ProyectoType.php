<?php


namespace App\Form;

use App\Entity\Clientes;
use App\Entity\Proyecto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProyectoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cliente', EntityType::class, [
                'class' => Clientes::class,
                'choice_label' => function (Clientes $cliente) {
                    $nombre = trim(($cliente->getNombreCl() ?? '') . ' ' . ($cliente->getApellidosCl() ?? ''));
                    return $nombre !== '' ? $nombre : 'Cliente #' . $cliente->getId();
                },
                'label' => 'Cliente',
                'placeholder' => 'Selecciona un cliente',
                'required' => false,
                'attr' => [
                    'aria-describedby' => 'proyecto-cliente-ayuda',
                ],
            ])
            ->add('nombre', TextType::class, [
                'label' => 'Descripción del proyecto',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ej.: Reforma del piso de la calle Corrida 12',
                    'aria-describedby' => 'proyecto-nombre-ayuda',
                ],
            ])
            ->add('tipoObra', ChoiceType::class, [
                'label' => 'Tipo de obra',
                'placeholder' => 'Selecciona el tipo de obra',
                'required' => false,
                'choices' => [
                    'Plato de ducha' => 'plato_ducha',
                    'Baño' => 'bano',
                    'Mampara' => 'mampara',
                    'Mueble' => 'mueble',
                    'Otros' => 'otros',
                ],
                'help' => 'Una clasificación opcional para encontrar el proyecto con más facilidad.',
            ])
            ->add('fechaInicio', DateType::class, [
                'label' => 'Fecha de inicio',
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('fechaFinPrevista', DateType::class, [
                'label' => 'Fecha fin prevista',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('notas', TextareaType::class, [
                'label' => 'Notas',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Observaciones internas del proyecto...',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Proyecto::class,
        ]);
    }
}
