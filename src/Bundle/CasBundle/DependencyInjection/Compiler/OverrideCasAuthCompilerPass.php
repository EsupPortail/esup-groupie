<?php
/*
 * Copyright 2022, ESUP-Portail  http://www.esup-portail.org/
 *  Licensed under APACHE2
 *  @author  Laure DENOIX
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */


namespace App\Bundle\CasBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;


class OverrideCasAuthCompilerPass implements CompilerPassInterface
{

    /**
     * Overwrite project specific services
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $defNewService = $container->getDefinition('phpcasguard.cas_authenticator');
        $defNewService ->setClass('App\Bundle\CasBundle\Security\AmuCasAuthenticator');

    }
}