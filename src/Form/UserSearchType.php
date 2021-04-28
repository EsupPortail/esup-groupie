<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;


class UserSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
             ->add('uid', TextType::class, array('label' => 'Identifiant', 'required' => false))
             ->add('sn', TextType::class, array('label' => 'Nom', 'required' => false))
             ->add('exacte', CheckboxType::class, array('label' => 'Recherche exacte', 'required'  => false)
                     )   
             ->getForm(); 

    }
    public function setDefaultOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
                                     'data_class' =>  User::class
                                     ));
    }
    public function getName()
    {
        return 'usersearch';
    }
}