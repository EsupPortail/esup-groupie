<?php

namespace App\Form;

use App\Entity\Group;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroupSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
             ->add('cn',TextType::class, array('label' => 'Nom ',
                                         'required' => true
                                         ))
            ->add('flag', HiddenType::class, array(
                  'data' => '0'))
            ->getForm();

    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array('data_class' => Group::class)
                               );
    }
    public function getName()
    {
        return 'groupsearch';
    }
}