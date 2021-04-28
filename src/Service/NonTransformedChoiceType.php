<?php

namespace App\Service;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class NonTransformedChoiceType extends ChoiceType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $builder->resetModelTransformers();
        $builder->resetViewTransformers();
    }
}