<?php
/*
 * Copyright 2022, ESUP-Portail  http://www.esup-portail.org/
 *  Licensed under APACHE2
 *  @author  Peggy FERNANDEZ BLANCO <peggy.fernandez-blanco@univ-amu.fr>
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */

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