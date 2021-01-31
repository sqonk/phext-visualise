<?php
declare(strict_types=1);
/**
*
* Plotting Library
* 
* @package		phext
* @subpackage	detach
* @version		1
* 
* @license		MIT see license.txt
* @copyright	2019 Sqonk Pty Ltd.
*
*
* This file is distributed
* on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
* express or implied. See the License for the specific language governing
* permissions and limitations under the License.
*/

use PHPUnit\Framework\TestCase;
use sqonk\phext\visualise\Visualiser;



class VisualiserTest extends TestCase
{
    public function testJavaClassCompilation() {
        $v = new Visualiser;
        $this->assertSame(true, $v->compiled());
    }
    
    public function testOpenNewWindowAndClose()
    {
        $v = new Visualiser;
        $id = $v->open(title:'test', width:300, height:300, posX:20, posY:50);
        $this->assertSame(1, $id);
        
        $this->assertSame([300, 300, 20, 50, 1], $v->info(windowID:$id));
        
        
        $this->assertSame(true, $v->close(windowID:$id));
    }
}