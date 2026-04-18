<?php

namespace App\Form;

use App\Entity\Documento;
use App\Entity\ProyectoGasto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProyectoGastoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $documentos = $options['documentos_proyecto'];

        $builder
            ->add('categoria', ChoiceType::class, [
                'label' => 'Categoría *',
                'choices' => [
                    'Materiales' => 'materiales',
                    'Mano de obra externa' => 'mano_obra_externa',
                    'Transporte' => 'transporte',
                    'Escombro' => 'escombro',
                    'Subcontrata' => 'subcontrata',
                    'Incidencia' => 'incidencia',
                    'Herramienta' => 'herramienta',
                    'Varios' => 'varios',
                ],
                'placeholder' => 'Selecciona categoría',
            ])
            ->add('concepto', TextType::class, [
                'label' => 'Concepto *',
                'attr' => [
                    'placeholder' => 'Ej: Pago a alicatador',
                ],
            ])
            ->add('proveedor', TextType::class, [
                'label' => 'Proveedor / persona',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ej: Juan / Proveedor X',
                ],
            ])
            ->add('documento', EntityType::class, [
                'class' => Documento::class,
                'choices' => $documentos,
                'choice_label' => function (Documento $documento) {
                    return sprintf(
                        '%s-%04d (%s)',
                        $documento->getSerie(),
                        $documento->getNumero(),
                        ucfirst($documento->getTipoDocumento() ?? 'documento')
                    );
                },
                'label' => 'Documento relacionado',
                'required' => false,
                'placeholder' => 'Sin documento asociado',
            ])
            ->add('fechaPrevista', DateType::class, [
                'label' => 'Fecha prevista *',
                'widget' => 'single_text',
            ])
            ->add('importePrevisto', MoneyType::class, [
                'label' => 'Importe previsto *',
                'currency' => 'EUR',
                'divisor' => 1,
            ])
            ->add('estado', ChoiceType::class, [
                'label' => 'Estado *',
                'choices' => [
                    'Previsto' => 'previsto',
                    'Confirmado' => 'confirmado',
                    'Pagado' => 'pagado',
                    'Cancelado' => 'cancelado',
                ],
            ])
            ->add('fechaReal', DateType::class, [
                'label' => 'Fecha real',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('importeReal', MoneyType::class, [
                'label' => 'Importe real',
                'currency' => 'EUR',
                'required' => false,
                'divisor' => 1,
            ])
            ->add('generaForecast', CheckboxType::class, [
                'label' => 'Generar movimiento en forecast',
                'required' => false,
            ])
            ->add('notas', TextareaType::class, [
                'label' => 'Notas',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Observaciones internas...',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProyectoGasto::class,
            'documentos_proyecto' => [],
        ]);

        $resolver->setAllowedTypes('documentos_proyecto', 'array');
    }
}