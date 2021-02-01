<?php
/**
*
* Sine Wave example
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

// Creates a simple sine wave animation with FPS counter.

require_once __DIR__.'/../vendor/autoload.php';

use sqonk\phext\visualise\Visualiser;

$visualiser = new Visualiser;

function adjust($sq): void {
    $sq->size = $sq->dir ? $sq->size + $sq->step : $sq->size - $sq->step;
    if ($sq->size > 350)
        $sq->dir = false;
    else if ($sq->size < 5)
        $sq->dir = true;
}

$Sq = named_objectify('size', 'dir', 'step', 'r', 'g', 'b');
$squares = [ 
  $Sq(5, true, 10, 147,17,50),  
  $Sq(20, true, 5, 148,32,146), 
  $Sq(20, true, 15, 0,0,200)
];
foreach ($visualiser->animate(400, 400, title:'Squares', frames:1000, posX:20, posY:50) as $count => $img)
{
    # prefill white background
    $white = imagecolorallocate($img, 255,255,255);
    imagefilledrectangle(image:$img, x1:0, y1:0, x2:399, y2:399, color:$white);
    
    imagesetthickness($img, 3);
    foreach ($squares as $s) {
        $r = $s->size / 2;
        $clr = imagecolorallocate(image:$img, red:$s->r, green:$s->g, blue:$s->b);
        
        imagerectangle(image:$img, x1:200-$r, y1:200-$r, x2:200+$r, y2:200+$r, color:$clr);
        
        adjust($s);
    }   
}

println('completed.');

