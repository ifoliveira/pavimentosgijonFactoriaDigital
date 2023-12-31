<?php

namespace App\Form;

use App\Entity\Encuesta;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class EncuestaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
                ->add('p1', ChoiceType::class, [
                    'choices'  => [
                            'Busqueda por internet' => 'Internet',
                            'Recomendación de un amigo / familiar' => 'Recome',
                            'Suelo caminar por la avenida Schultz' => 'Calle',
                            'Otros' => 'Otros'
                    ],
                    'expanded' => true,
                    'multiple' => false,
                    'label' => "¿Como conociste Pavimentos Gijón?",
                    ])

                    ->add('p2', ChoiceType::class, [
                        'choices'  => [
                            '1' => 1,
                            '2' => 2,
                            '3' => 3,
                            '4' => 4,
                            '5' => 5,
                            '6' => 6,
                            '7' => 7,
                            '8' => 8,
                            '9' => 9,
                            '10' => 10,
                        ],
                        'expanded' => true,
                        'multiple' => false,
                        'label' => "De 1 a 10 ¿Como de satisfecho estuviste con la atención recibida?",
                        ])      
                        

                        ->add('p3', ChoiceType::class, [
                            'choices'  => [
                                '1' => 1,
                                '2' => 2,
                                '3' => 3,
                                '4' => 4,
                                '5' => 5,
                                '6' => 6,
                                '7' => 7,
                                '8' => 8,
                                '9' => 9,
                                '10' => 10,
                            ],
                            'expanded' => true,
                            'multiple' => false,
                            'label' => "¿Y con el presupuesto?",
                            ])   

                            ->add('p4', ChoiceType::class, [
                                'choices'  => [
                                        'Si' => 'Si',
                                        'No' => 'No',
                                        'No hice la reforma' => 'Sinrefor',
                                ],
                                'expanded' => true,
                                'multiple' => false,
                                'label' => "¿Aceptaste el presupuesto?",
                                ])

                            ->add('p5', ChoiceType::class, [
                                'choices'  => [
                                    'Precio' => "Precio",
                                    'Confianza' => 'Confianza',
                                    'Profesionalidad' => "Profesionalidad",
                                    'Atención' => 'Atención',
                                    'Claridad en el presupuesto' => 'Claridad en el presupuesto',
                                    'Tiempo de espera para iniciar la reforma' => 'Tiempo de espera para iniciar la reforma',
                                    'Tiempo total para finalizar la reforma' => 'Tiempo total para finalizar la reforma',
                                    'Calidad de los materiales' => 'Calidad de los materiales',
                                    'Nos habían recomendado' => 'Nos habían recomendado',
                                    ],
                                    'expanded' => true,
                                    'multiple' => true,
                                    'label' => "¿Que valoraste mejor? (puedes marcar más de una)",
                                    ])  
                            ->add('p6', ChoiceType::class, [
                                'choices'  => [
                                    'Precio' => "Precio",
                                    'Confianza' => 'Confianza',
                                    'Profesionalidad' => "Profesionalidad",
                                    'Atención' => 'Atención',
                                    'Claridad en el presupuesto' => 'Claridad en el presupuesto',
                                    'Tiempo de espera para iniciar la reforma' => 'Tiempo de espera para iniciar la reforma',
                                    'Tiempo total para finalizar la reforma' => 'Tiempo total para finalizar la reforma',
                                    'Calidad de los materiales' => 'Calidad de los materiales',
                                    'Nos habían recomendado' => 'Nos habían recomendado',
                                    ],
                                    'expanded' => true,
                                    'multiple' => true,
                                    'label' => "¿Que valoraste peor? (puedes marcar más de una)",
                                    ])     

                        ->add('p7', ChoiceType::class, [
                            'choices'  => [
                                '1' => 1,
                                '2' => 2,
                                '3' => 3,
                                '4' => 4,
                                '5' => 5,
                                '6' => 6,
                                '7' => 7,
                                '8' => 8,
                                '9' => 9,
                                '10' => 10,
                            ],
                            'expanded' => true,
                            'multiple' => false,
                            'label' => "Nos recomendarías para una reforma (1 - No nunca o 10 - Si lo hago habitualmente)",
                            ])   

                           ->add('Enviar_formulario', SubmitType::class, [
                                'attr' => ['class' => 'save'],                                                                                                                               
                                ])   

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Encuesta::class,
        ]);
    }
}
