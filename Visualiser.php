<?php declare(strict_types = 1);

namespace sqonk\phext\visualise;

use \GDImage;

class Visualiser
{
    protected $stdin;
    protected $stdout;
    protected $stderr;
    
	protected bool $alive = false;
	protected string $inboundBuffer = "";
    protected array $windows = [];
    
    protected $quitCallback;
    
    protected const NEW_WINDOW = 1;
    protected const CLOSE_WINDOW = 2;
    protected const UPDATE_IMG = 3;
    
    protected const TERMINATOR = "\0\0";
    protected const BOUNDARY = "#--0--#";
    
    public function __construct()
    {
        $dir = __DIR__."/build";
        $javaFile = __DIR__."/PHEXTVisualiser.java";
        if (! file_exists($javaFile))
            throw new \RuntimeException("The PHEXT Visualiser java file is missing. Please reinstall the package.");
        
        $classFile = "$dir/PHEXTVisualiser.class";
        if (! file_exists($classFile)) {
            error_log('compiling java class..');
            `javac -d $dir -encoding UTF8 $javaFile`;
        }
        
        println('spinning up visualiser instance..');
        
		$fdSpec = [
		    ['pipe', 'r'], // stdin
		    ['pipe', 'w'], // stdout
		    ['file', __DIR__.'/error-output.txt', 'a'], // stderr
		];
        
        
		$this->process = proc_open("java -cp $dir PHEXTVisualiser", $fdSpec, $pipes, getcwd());
		if (! is_resource($this->process)) {
		    throw new \RuntimeException('Unable to launch a Visualiser instance. Perhaps there is a problem with your Java installation or your system is starved of resources?');
		}
        
        $this->alive = true;
        [$this->stdin, $this->stdout] = $pipes;
		//stream_set_blocking($this->stdin, false);
		//stream_set_blocking($this->stdout, false);
		//stream_set_blocking($this->stderr, false);
    }
    
	public function __destruct()
	{
		if ($this->alive)
			$this->terminate();
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
			//fclose($this->stderr);
			
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
		else
		{
			//if ($err = fread($this->stderr, 1024*1024))
			//	error_log($err);
		}
		return true;
	}
	
	protected function _send(int $command, string $data, bool $expectReply): ?string
	{
		if (! $this->_status()) {
		    error_log('status check failed, process not running.');
            return null;
		}
			
		fwrite($this->stdin, "{$command}$data".self::TERMINATOR);
		fflush($this->stdin);
        
        return $expectReply ? $this->_waitForResponse() : null;
	}
    
	/**
	 * Read the latest event sent downstream from the Visualiser app.
	 */
	public function _waitForResponse(): ?string
	{
        println('waiting for reply..');
        while (! str_ends_with($this->inboundBuffer, self::TERMINATOR))
        {
            if ($read = fread($this->stdout, 1024*1024)) {
                $this->inboundBuffer .= $read;
            }
        }
		$data = substr($this->inboundBuffer, 0, -strlen(self::TERMINATOR));
        $this->inboundBuffer = '';     
		return $data;
	}
    
	// ==============
	// = public API =
	// ==============
	
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
    
    public function open(string $title, int $width, int $height, int $imageCount = 1): ?int
    {
        $config = implode(self::BOUNDARY, [$title, $width, $height, $imageCount]);
        $resp = $this->_send(command:self::NEW_WINDOW, data:$config, expectReply:true);
        return $resp ? (int)$resp : null;
    }
	
    public function update(int $windowID, GDImage|string $image = null, ?array $images = null): void
    {
        if ($images) {
            $converted = array_map(function($img) {
                $str = is_string($img) ? $img : $this->_convertGD($img);
                return base64_encode($str);
            }, $images);
        }
        
        else if ($image) {
            $converted = is_string($image) ? [ base64_encode($image) ] : [ base64_encode($this->_convertGD($image)) ];
        }
            
        else
            throw new Exception('Either the $image or $images parameter must be set.');
        
        $config = $windowID.self::BOUNDARY.implode(self::BOUNDARY, $converted);
        $this->_send(command:self::UPDATE_IMG, data:$config, expectReply:false);
    }
    
    private function _convertGD(GDImage $image): string
    {
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_contents();
        ob_end_clean();
        return $jpeg;
    }
}

