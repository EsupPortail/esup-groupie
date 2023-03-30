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


use Doctrine\ORM\Query\AST\Functions\FunctionNode;

class StringUtils extends FunctionNode
{
    /**
     * Remplace TOUS les caractères accentués par leur homologue sans accents, et
     * remplace par un [espace] les caractères spéciaux des noms composé tels que : ".-'"
     * @param string $string la chaine de carcatère à convertir
     * @param bool $utf8decode réaliser le décodage utf8 avant la convertion (si donnée issu de ORACLE...)
     * @return string
     */
    public function normEnleveAccents($string, $utf8decode = false, $utf8encode = false) {
        if ($utf8decode){
            $string = utf8_decode($string);
        }
        if ($utf8encode){
            $string = utf8_encode($string);
        }

        $arReplacer=array(
            "ä"=>"a", "à"=>"a",
            "À"=>"A", "Á"=>"A", "Â"=>"A", "Ã"=>"A", "Ä"=>"A",
            "é"=>"e", "è"=>"e", "ê"=>"e", "ë"=>"e",
            "È"=>"E", "É"=>"E", "Ê"=>"E", "Ë"=>"E",
            "ï"=>"i", "î"=>"i",
            "Ì"=>"I", "Í"=>"I", "Î"=>"I", "Ï"=>"I",
            "ô"=>"o", "ö"=>"o",
            "Ò"=>"O", "Ó"=>"O", "Ô"=>"O", "Ö"=>"O",
            "ù"=>"u", "û"=>"u", "ü"=>"u",
            "Ù"=>"U", "Ú"=>"U", "Û"=>"U", "Ü"=>"U",
            "ç"=>"c",
            "Ç"=>"C",
            "."=>"", "-"=>"", "'"=>"",
        );
        $string = strtr($string, $arReplacer);
        return $string;
    }

    /**
     * @inheritDoc
     */
    public function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker)
    {
        // TODO: Implement getSql() method.
    }

    /**
     * @inheritDoc
     */
    public function parse(\Doctrine\ORM\Query\Parser $parser)
    {
        // TODO: Implement parse() method.
    }
}