<?php declare(strict_types = 1);

namespace sqonk\phext\visualise;

/**
*
* Visualise
* 
* @package		phext
* @subpackage	visualise
* @version		1
* 
* @license		MIT see license.txt
* @copyright	2019-2021 Sqonk Pty Ltd.
*
*
* This file is distributed
* on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
* express or implied. See the License for the specific language governing
* permissions and limitations under the License.
*/


use \GDImage;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use sqonk\phext\core\arrays;

define('PHEXT_BIG_ENDIAN', pack('L', 1) === pack('N', 1));

// Pack for forced BIG ENDIAN byte order.
function be_pack(string $format, $value): string {
    $packed = pack($format, $value);
    if (! PHEXT_BIG_ENDIAN) {
        $packed = strrev($packed);
    }
    return $packed;
}

/**
 * The primary class of the library. A single Visualiser instance can be used to control all required 
 * windows and image displays for a single program/script.
 * 
 * It contains methods for opening and closing windows as well as pushing all rendered images to them. 
 */
class Visualiser
{
    protected $stdin;
    protected $stdout;
    protected $stderr;
    
	protected bool $alive = false;
	protected string $inboundBuffer = "";
    protected string $pathPrefix = '';
    protected $quitCallback = '';
    protected array $registeredWindows = [];
    
    protected const NEW_WINDOW = 1;
    protected const CLOSE_WINDOW = 2;
    protected const UPDATE_IMG = 3;
    protected const WINDOW_INFO = 4;
    protected const CHECKSUMS = 5;
    
    protected const VERSION = 10;
    
    // Used for unit testing. Do not enable this for any other means as it increases memory usage.
    static public bool $_testing = false;
    
    /**
     * Create a new visualiser instance capable of spawning its own set of windows.
     * 
     * -- parameters:
     * @param $logJavaErrorsToFile The PHEXTVisualiser java class logs all exceptions and errors to the StdErr stream. When this parameter is set to TRUE all such errors will be logged to a file in the current working directory. When set to FALSE the same errors will be printed to the console instead. Defaults to FALSE.
     */
    public function __construct(bool $logJavaErrorsToFile = false)
    {
        [$build, $dir] = $this->_buildDir();
        
        $javaFile = __DIR__."/PHEXTVisualiser.java";
        if (! file_exists($javaFile))
            throw new \RuntimeException("The PHEXT Visualiser java file is missing. Please reinstall the package.");
        
        if (! file_exists($build))
            mkdir($build);
        
        if (! file_exists($dir))
        {
            // Remove all older versions and create the dir for the current.
            $rdi = new RecursiveDirectoryIterator($build, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) 
            {
                if ($file->isDir())
                    rmdir($file->getPathname());
                else
                    unlink($file->getPathname());
            }
            
            mkdir($dir); 
        }
        
        $classFile = "$dir/PHEXTVisualiser.class";
        if (! file_exists($classFile)) {
            error_log('compiling java class..');
            `{$this->pathPrefix}javac -d $dir -encoding UTF8 $javaFile`;
        }
        
        error_log('spinning up visualiser instance..');
        
		$fdSpec = [
		    ['pipe', 'r'], // stdin
		    ['pipe', 'w'], // stdout
		];
        if ($logJavaErrorsToFile)
            $fdSpec[] = ['file', __DIR__.'/error-output.txt', 'a'];
        else
            $fdSpec[] = ['pipe', 'w']; // stderr pipe
        
		$this->process = proc_open("{$this->pathPrefix}java -cp $dir PHEXTVisualiser", $fdSpec, $pipes, getcwd());
		if (! is_resource($this->process)) {
		    throw new \RuntimeException('Unable to launch a Visualiser instance. Perhaps there is a problem with your Java installation or your system is starved of resources?');
		}
        
        $this->alive = true;
        [$this->stdin, $this->stdout] = $pipes;
        
        if (count($pipes) == 3) {
            $this->stderr = $pipes[2];
            stream_set_blocking($this->stderr, false);
        }
        stream_set_blocking($this->stdout, false);
    }
    
	public function __destruct()
	{
		if ($this->alive)
			$this->terminate();
	}
    
    private function _buildDir(): array
    {
        $build = __DIR__."/.build/";
        $dir = "$build/".self::VERSION;
        return [$build, $dir];
    }
	
    /**
     * Kill the visualiser instance, closing all associated windows.
     */
	public function terminate(): void
	{
		if ($this->alive)
		{
			fclose($this->stdin);
			fclose($this->stdout);
            if ($this->stderr)
			    fclose($this->stderr);
			
			proc_terminate($this->process);
			$this->alive = false;
		}
	}
    
	// =============
	// = Internals =
	// =============
	
	protected function _status(): bool
	{
		$info = proc_get_status($this->process);
		if (! $info['running']) {
			$this->terminate();
            
			if ($this->quitCallback)
			    ($this->quitCallback)();
            
			return false;
		}
		else {
			$this->_checkStdErr();
		}
		return true;
	}
    
    // Check for data in the error stream. Returns TRUE if something was found.
    protected function _checkStdErr(): bool
    {
        if ($this->stderr)
        {
            $err = '';
			while ($str = fread($this->stderr, 1024*1024))
				$err .= $str;
            
            if ($err)
                error_log($err);
            
            return true;
        }
        return false;
    }
	
	protected function _send(int $command, string $data, bool $expectReply): ?string
	{
		if (! $this->_status()) {
		    error_log('status check failed, process not running.');
            return null;
		}
			
		fwrite($this->stdin, be_pack('l', $command).$data);
		fflush($this->stdin);
        
        return $expectReply ? $this->_waitForResponse() : null;
	}
    
    // Convert a GD Image object into an encoded JPEG string.
    private function _convertGD(GDImage $image): string
    {
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_contents();
        ob_end_clean();
        return $jpeg;
    }
    
	/**
	 * Read the latest event sent downstream from the Visualiser app.
	 */
	protected function _waitForResponse(): ?string
	{
        while (strlen($this->inboundBuffer) == 0)
        {
            while ($read = fread($this->stdout, 1024*1024)) {
                $this->inboundBuffer .= $read;
            }

            if (! $read) {
                $this->_checkStdErr();
                usleep(5);
            }
        }
        $data = $this->inboundBuffer;
        $this->inboundBuffer = '';     
		return $data;
	}
    
    protected function _verifyID(int $id)
    {
        if (! arrays::contains($this->registeredWindows, $id))
            throw new \InvalidArgumentException("There is no window for ID: $id");
    }
    
    /**
     * Internal use for unit testing, will not function outside of testing mode.
     */
    public function _getCheckSums(int $windowID) : ?array
    {
        $this->_verifyID($windowID);
        
         if ($resp = $this->_send(command:self::CHECKSUMS, data:be_pack('l', $windowID), expectReply:true)) {
             return explode('|', $resp);
         }
         
         return null;
    }
    
	// ==============
	// = public API =
	// ==============
	
    /**
     * If your command line environment does not have the java and javac tools in its search paths
     * then you can set the absolute directory path to them.
     */
    public function set_java_path(string $pathPrefix): void
    {
        if (! str_ends_with($pathPrefix, '/'))
            $pathPrefix .= '/';
        $this->pathPrefix = $pathPrefix;
    }
    
    /**
     * Determines if the java class been successfully compiled.
     */
    public function compiled(): bool
    {
        [$build, $dir] = $this->_buildDir();
        $classFile = "$dir/PHEXTVisualiser.class";
        return file_exists($classFile);
    }
    
    /**
     * Provide a callback that is run in the event that the Visualiser app is terminated 
     * by the user or by some other means outside of the script.
     * 
     * Your callback method will not take any parameters.
     */
	public function on_termination(callable $callback): void
	{
		if (is_string($callback) and ! function_exists($callback))
			throw new \InvalidArgumentException("function '$callback' does not exist.");
        
		$this->quitCallback = $callback;
	}
    
    /**
     * Open a new window capable of displaying the given number of images. The images are automatically laid out
     * within a grid in the resulting window. 
     * 
     * -- parameters:
     * @param $title A title to be displayed at the top of the window.
     * @param $width The width of the window.
     * @param $height The height of the window.
     * @param $imageCount The exact amount of images that will be displayed within the window.
     * @param $posX Starting X co-ordinate the window will be opened on.
     * @param $posY Starting Y co-ordinate the window will be opened on.
     * 
     * @return The unique identifier for the window. This is used to subsequently push image updates in via the `update` method.
     */
    public function open(string $title, int $width, int $height, int $imageCount = 1, int $posX = -1, int $posY = -1): ?int
    {
        $t = self::$_testing ? 1 : 0;
        $config = be_pack('l', $width).be_pack('l', $height).be_pack('l', $imageCount).
            be_pack('l', $posX).be_pack('l', $posY).be_pack('l', $t).be_pack('l', strlen($title)).$title;
        
        if ($resp = $this->_send(command:self::NEW_WINDOW, data:$config, expectReply:true)) {
            $id = (int)$resp;
            $this->registeredWindows[] = $id;
            return $id;
        }
        
        return null;
    }
    
    /**
     * Close the window with the given window ID, removing it from screen and releasing the memory associated with it.
     * 
     * -- parameters:
     * @param $windowID The unique ID of the window that the images will be displayed in. This is obtained when the window is first created using the `open` method.
     * 
     * @throws InvalidArgumentException If there is no window present for the given window ID.
     * 
     * @return TRUE on success.
     */
    public function close(int $windowID): bool
    {
        $this->_verifyID($windowID);
        return (bool)$this->_send(command:self::CLOSE_WINDOW, data:be_pack('l', $windowID), expectReply:true);
    }
    
    /**
     * Retrieve information about the window with the given window ID, such as dimensions, location and image count.
     * 
     * -- parameters:
     * @param $windowID The unique ID of the window that the images will be displayed in. This is obtained when the window is first created using the `open` method.
     * 
     * @throws Exception if the response does not yield the correct amount of items, or an irregular response.
     * @throws InvalidArgumentException If there is no window present for the given window ID.
     * 
     * @return An array containing the window width, height, x coordinate, y coordinate and image count. Will return NULL if no response is received.
     */
    public function info(int $windowID) : ?array
    {
         $this->_verifyID($windowID);
        
         if ($resp = $this->_send(command:self::WINDOW_INFO, data:be_pack('l', $windowID), expectReply:true)) {
             $items = explode('|', $resp);
             if (count($items) != 5)
                 throw new \Exception("Received unexpected response when retrieving window information.");
             
             foreach ($items as $i => &$val)
                 $items[$i] = (int)$val;
             
             return $items;
         }
         
         return null;
    }
    
	
    /**
     * Push a set of updated images to the window with the given window ID. It takes either a GDImage object
     * or an already encoded image in the form of a string (e.g. data loaded in from file or a URL).
     * 
     * Standard web formats should be supported such as JPEG, PNG or GIF.
     * 
     * The amount of images supplied should exactly match the amount of images the window was initially
     * configured to take.
     * 
     * -- parameters:
     * @param $windowID The unique ID of the window that the images will be displayed in. This is obtained when the window is first created using the `open` method.
     * @param $image A single image to be supplied to the window. Either a GDImage object or an already encoded string of the image data. If the $images array is also supplied then this parameter is ignored. This parameter should be used when the window has only one image.
     * @param $images An array of images to be supplied to the window. The contents of which should either consist of GDImage objects or already encoded string representations. Use this parameter when the window is configured to take multiple images.
     * 
     * @throws InvalidArgumentException If there is no window present for the given window ID.
     */
    public function update(int $windowID, GDImage|string $image = null, ?array $images = null): void
    {
        $this->_verifyID($windowID);
        
        $convert = function($img) {
            $img_str = ($img instanceof GDImage) ? $this->_convertGD($img) : $img;
            return be_pack('l', strlen($img_str)).$img_str;
        }; 
        
        if ($images) {
            //$converted = array_map(fn($img) => base64_encode($convert($img)), $images);
            $converted = implode('', array_map($convert, $images));
        }
        
        else if ($image) {
            $converted = $convert($image);
        }
            
        else
            throw new Exception('Either the $image or $images parameter must be set.');
                
        $data = be_pack('l', $windowID).$converted;
        $this->_send(command:self::UPDATE_IMG, data:$data, expectReply:true);
    }
    
    /**
     * Start a generator loop, with each cycle generating a new image frame to be drawn on, then rendered 
     * to an automatically created window. The image supplied is a full colour GDImage object. Upon completion 
     * of the iteration the image will be pushed to the window and displayed. 
     * 
     * The loop will run until the frame limit is reached or the loop is broken via some other means.
     * 
     * -- parameters:
     * @param $width The width of the window.
     * @param $height The height of the window. 
     * @param $frames When greater than 0, the total amount of images that will be pushed to the window before the loop exits. Omit or pass in 0 to have the loop continue indefinitely.
     * @param $title A title to be displayed at the top of the window.
     * @param $posX Starting X co-ordinate the window will be opened on.
     * @param $posY Starting Y co-ordinate the window will be opened on.
     */
    public function animate(int $width, int $height, int $frames = 0, string $title = '', int $posX = -1, int $posY = -1)
    {
        $id = $this->open(title:$title, width:$width, height:$height, posX:$posX, posY:$posY);
        
        $i = 0;
        while ($frames == 0 or ($frames > 0 && $i < $frames))
        {
            if (! $img = imagecreatetruecolor($width, $height))
                throw new \RuntimeException("A new image could not be created.");
            
            yield $i => $img;
            
            $this->update(windowID:$id, image:$img);
            $i++;
        }
    }
}

