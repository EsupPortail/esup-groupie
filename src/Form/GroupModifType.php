<?php

namespace App\Form;

use App\Entity\Group;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class GroupModifType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
             ->add('cn', TextType::class, array(
                                        'label' => 'Nom',
                                         'required' => true
                                         ))

            ->add('description', TextType::class, array(
                                             'required' => true
                                             ))

            ->add('amugroupfilter', TextType::class, array(
                                                'required' => false
                                                ))
            ->getForm();

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array('data_class' => Group::class)
                               );
    }

    public function getName()
    {
        return 'group_modify';
    }
}