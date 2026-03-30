<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbilityEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('textFr', TextareaType::class, [
                'label'    => 'Français',
                'required' => false,
                'attr'     => ['rows' => 2],
            ])
            ->add('textEn', TextareaType::class, [
                'label'    => 'Anglais',
                'required' => false,
                'attr'     => ['rows' => 2],
            ])
            ->add('textDe', TextareaType::class, [
                'label'    => 'Allemand',
                'required' => false,
                'attr'     => ['rows' => 2],
            ])
            ->add('textEs', TextareaType::class, [
                'label'    => 'Espagnol',
                'required' => false,
                'attr'     => ['rows' => 2],
            ])
            ->add('textIt', TextareaType::class, [
                'label'    => 'Italien',
                'required' => false,
                'attr'     => ['rows' => 2],
            ])
            ->add('isSupport', CheckboxType::class, [
                'label'    => 'Support',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);

        $resolver->setAllowedTypes('data_class', ['null', 'string']);
    }
}
