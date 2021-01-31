<?php
/**
*
* Langton's Ants example
* 
* @package		phext
* @subpackage	visualise
* @version		1
* 
* @license		MIT see license.txt
* @copyright	2021 Sqonk Pty Ltd.
*
*
* This file is distributed
* on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
* express or implied. See the License for the specific language governing
* permissions and limitations under the License.
*/

/*
    A basic implementation of Langton's Ant.
    https://en.wikipedia.org/wiki/Langton%27s_ant

    Creates a animation that shows the live progress of the ants as they move
    over the canvas.
*/
require_once __DIR__.'/../vendor/autoload.php';

define('CANVAS_SIZE', 300);
define('ANT_SIZE', 5);

use sqonk\phext\visualise\Visualiser;


class Ant {
    
    public int $x;
    public int $y;
    
    private int $dir = 0;
    
    public function __construct() {
        // start the ants off somewhere in the centre.
        $this->x = (CANVAS_SIZE / 2) + rand(-(ANT_SIZE * 2), ANT_SIZE * 2);
        $this->y = (CANVAS_SIZE / 2) + rand(-(ANT_SIZE * 2), ANT_SIZE * 2);
    }
    
    public function right(): void {
        $this->dir += 90;
        if ($this->dir > 360)
            $this->dir -= 360;
    }
    
    public function left(): void {
        $this->dir -= 90;
        if ($this->dir < 0)
            $this->dir += 360;
    }
    
    public function forward(): void {
        
		$dx = ANT_SIZE * sin(deg2rad($this->dir));
		$dy = ANT_SIZE * cos(deg2rad($this->dir));
		if (abs($dx) < 0.001) $dx = 0;
		if (abs($dy) < 0.001) $dy = 0;
        $this->x += (int)$dx;
        $this->y += (int)$dy;
        
        // teleportation.
        if ($this->x < 0) {
            $this->x = CANVAS_SIZE-ANT_SIZE-1;
        }
        else if ($this->x > CANVAS_SIZE-1)
            $this->x = 0;
        
        if ($this->y < 0) {
            $this->y = CANVAS_SIZE-ANT_SIZE-1;
        }
        else if ($this->y > CANVAS_SIZE-1)
            $this->y = 0;
    }   
}

function main()
{
    $ants = array_map(fn() => new Ant, range(1, 2));
        
    $visualiser = new Visualiser;
    $visualiser->on_termination(fn() => exit); // Exit script if GUI is quit.
    
    $id = $visualiser->open(title:'Langtons Ants', width:CANVAS_SIZE, height:CANVAS_SIZE);
    
    $img = imagecreatetruecolor(CANVAS_SIZE, CANVAS_SIZE);
    $black = imagecolorallocate($img, 0,0,0);
    $white = imagecolorallocate($img, 255,255,255);
    
    # fill with white background
    imagefilledrectangle(image:$img, x1:0, y1:0, x2:CANVAS_SIZE-1, y2:CANVAS_SIZE-1, color:$white);
    
    while (true)
    {
        # progress the ants, render immediate state change and move them accordingly.
        foreach ($ants as $ant) 
        {
            $rgb = imagecolorat($img, $ant->x, $ant->y);
            [$r, $g, $b] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];

            if ($r == 255 && $g == 255 && $b == 255) {
                $ant->right();
                $clr = $black;
            }
            else {
                 $ant->left();
                 $clr = $white;
            }
 
            if (ANT_SIZE == 1)
                imagesetpixel(image:$img, x:$ant->x, y:$ant->y, color:$clr);
            else
                imagefilledrectangle(image:$img, x1:$ant->x, y1:$ant->y, 
                    x2:$ant->x+ANT_SIZE-1, y2:$ant->y+ANT_SIZE-1, color:$clr);
        
            $ant->forward();
        }
        
        $visualiser->update(windowID:$id, image:$img);
    }
}


main();
