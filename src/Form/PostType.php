<?php
// src/Form/PostType.php

namespace App\Form;

use App\Entity\Post;
use App\Entity\image;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class)
            ->add('slug', TextType::class)
            ->add('meta_description', TextareaType::class)
            ->add('header_h1', TextType::class)
            ->add('header_h2', TextType::class)
            ->add('header_h3', TextType::class)
            ->add('header_h4', TextType::class)
            ->add('content', TextareaType::class)
            ->add('published_at', DateTimeType::class)
            ->add('is_published', CheckboxType::class, [
                'required' => false,
                'data' => false, // Valor predeterminado cuando el campo no está presente en el formulario enviado
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }
}
?>