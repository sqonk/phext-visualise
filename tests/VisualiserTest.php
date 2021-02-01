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
    
    private function genImageSquare(int $x, int $y, int $r, int $g, int $b): string
    {
        $img = imagecreatetruecolor(300, 300);
        $white = imagecolorallocate($img, 255,255,255);
        $colour = imagecolorallocate($img, $r,$g,$b);
        
        $size = 20;
        imagefilledrectangle(image:$img, x1:0, y1:0, x2:299, y2:299, color:$white);
        imagefilledrectangle(image:$img, x1:$x, y1:$y, x2:$x+$size, y2:$y+$size, color:$colour);
        ob_start();
        imagejpeg($img);
        $jpeg = ob_get_contents();
        ob_end_clean();
        
        return $jpeg;
    }
    
    public function testOneImage()
    {
        Visualiser::$_testing = true;
        $v = new Visualiser;
        $id = $v->open(title:'test', width:300, height:350, posX:20, posY:50);

        $size = 20;
        foreach (range(5, 50) as $o) 
        {
            $jpeg = $this->genImageSquare($o, $o, 0,0,0);
            $v->update(windowID:$id, image:$jpeg);
            
            $this->assertSame(md5($jpeg), $v->_getCheckSums($id)[0]);
        }
        $v->close($id);
    }
    
    public function testTwoImages()
    {
        Visualiser::$_testing = true;
        $v = new Visualiser;
        $id = $v->open(title:'test', width:650, height:350, posX:20, posY:50, imageCount:2);

        $size = 20;
        foreach (range(5, 50) as $i) 
        {
            $img1 = $this->genImageSquare($i, 20, 255,0,0);
            $img2 = $this->genImageSquare(279-$i, 40, 0,0,255);
            
            $v->update(windowID:$id, images:[$img1, $img2]);
            
            $this->assertSame([md5($img1), md5($img2)], $v->_getCheckSums($id));
        }
        $v->close($id);
    }
    
    public function testFourImages()
    {
        Visualiser::$_testing = true;
        $v = new Visualiser;
        $id = $v->open(title:'test', width:650, height:750, posX:20, posY:50, imageCount:4);

        $size = 20;
        foreach (range(5, 50) as $i) 
        {
            $img1 = $this->genImageSquare($i, 20, 215,131,255);
            $img2 = $this->genImageSquare(279-$i, 40, 255,0,255);
            $img3 = $this->genImageSquare(50, $i+30, 100,100,100);
            $img4 = $this->genImageSquare(200, 250-$i, 255,147,0);
            
            $v->update(windowID:$id, images:[$img1, $img2, $img3, $img4]);
            
            $exp = [md5($img1), md5($img2), md5($img3), md5($img4)];
            $this->assertSame($exp, $v->_getCheckSums($id));
        }
        $v->close($id);
    }
    
    public function testMultiWindow()
    {
        Visualiser::$_testing = true;
        $v = new Visualiser;
        $win1 = $v->open(title:'window 1', width:650, height:350, posX:20, posY:50, imageCount:2);
        $win2 = $v->open(title:'window 2', width:300, height:350, posX:20, posY:400);

        $size = 20;
        foreach (range(5, 50) as $i) 
        {
            $img1 = $this->genImageSquare($i, 20, 215,131,255);
            $img2 = $this->genImageSquare(279-$i, 40, 255,0,255);
            $img3 = $this->genImageSquare(50, $i+30, 100,100,100);
            
            $v->update(windowID:$win1, images:[$img1, $img2]);
            $v->update(windowID:$win2, image:$img3);
            
            $exp = [md5($img1), md5($img2)];
            $this->assertSame($exp, $v->_getCheckSums($win1));
            $this->assertSame(md5($img3), $v->_getCheckSums($win2)[0]);
        }
        $v->close($win1);
        $v->close($win2);
    }
}