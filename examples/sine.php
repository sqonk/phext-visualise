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
$h = 248;

foreach ($visualiser->animate(400, 250, title:'sine wave', frames:1000, posX:20, posY:50) as $count => $img)
{
    $black = imagecolorallocate(image:$img, red:0, green:0, blue:0);
    
    $i = $count * 4;
    
    # prefill white background
    imagefilledrectangle(image:$img, x1:0, y1:0, x2:399, y2:249, color:imagecolorallocate($img, 255,255,255));

    # diagonal line
    $angle = (int)(sin(deg2rad($i)) * $h + $h);
    imageline(image:$img, x1:0, y1:$h / 2, x2:399, y2:$angle, color:$black);

    # sine wave
    foreach (range(0, 540, 2) as $x) {
        $y = floor(sin(deg2rad($x + $i)) * ($h / 2)) + 125;
        imagesetpixel(image:$img, x:$x / 2, y:$y, color:$black);
    }
}

println('completed.');

