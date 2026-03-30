<?php

namespace App\Form;

use App\Entity\AbilityTrigger;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EffectFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $keywordChoices = array_column($options['keyword_stats'], 'keyword');
        $keywordChoices = array_combine($keywordChoices, $keywordChoices);

        $builder
            ->add('q', TextType::class, [
                'label'    => 'Recherche',
                'required' => false,
                'attr'     => ['placeholder' => "Texte de l'effet..."],
            ])
            ->add('abilityTrigger', EntityType::class, [
                'label'        => 'Trigger',
                'class'        => AbilityTrigger::class,
                'choice_label' => fn(AbilityTrigger $t) => $t->getTextFr() ?? $t->getTextEn() ?? '—',
                'required'     => false,
                'placeholder'  => 'Tous',
                'attr'         => ['class' => 'select2'],
            ])
            ->add('keyword', ChoiceType::class, [
                'label'    => 'Keyword',
                'required' => false,
                'choices'  => array_merge(['Tous' => ''], $keywordChoices),
            ])
            ->add('linked', ChoiceType::class, [
                'label'    => 'Ability key',
                'required' => false,
                'choices'  => [
                    'Tous'        => '',
                    'Complète'    => 'complete',
                    'Incomplète'  => 'incomplete',
                    'Sans clef'   => 'none',
                ],
            ])
            ->add('condition', TextType::class, [
                'label'    => 'Condition',
                'required' => false,
                'attr'     => [
                    'placeholder'                => 'Si vous contrôlez...',
                    'autocomplete'               => 'off',
                    'data-controller'            => 'autocomplete',
                    'data-autocomplete-target'   => 'input',
                    'data-action'                => 'input->autocomplete#search',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'          => 'GET',
            'csrf_protection' => false,
            'keyword_stats'   => [],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
