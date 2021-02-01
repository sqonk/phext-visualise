###### PHEXT > [Visualise](../README.md) > [API Reference](index.md) > Visualiser
------
### Visualiser
The primary class of the library. A single Visualiser instance can be used to control all required windows and image displays for a single program/script.

It contains methods for opening and closing windows as well as pushing all rendered images to them.
#### Methods
[__construct](#__construct)
[__destruct](#__destruct)
[terminate](#terminate)
[_getCheckSums](#_getchecksums)
[set_java_path](#set_java_path)
[compiled](#compiled)
[on_termination](#on_termination)
[open](#open)
[close](#close)
[info](#info)
[update](#update)
[animate](#animate)

------
##### __construct
```php
public function __construct(bool $logJavaErrorsToFile = false) 
```
Create a new visualiser instance capable of spawning its own set of windows.

- **$logJavaErrorsToFile** The PHEXTVisualiser java class logs all exceptions and errors to the StdErr stream. When this parameter is set to `TRUE` all such errors will be logged to a file in the current working directory. When set to `FALSE` the same errors will be printed to the console instead. Defaults to `FALSE`.


------
##### __destruct
```php
public function __destruct() 
```
No documentation available.


------
##### terminate
```php
public function terminate() : void
```
Kill the visualiser instance, closing all associated windows.


------
##### _getCheckSums
```php
public function _getCheckSums(int $windowID) : ?array
```
Internal use for unit testing, will not function outside of testing mode.


------
##### set_java_path
```php
public function set_java_path(string $pathPrefix) : void
```
If your command line environment does not have the java and javac tools in its search paths then you can set the absolute directory path to them.


------
##### compiled
```php
public function compiled() : bool
```
Determines if the java class been successfully compiled.


------
##### on_termination
```php
public function on_termination(callable $callback) : void
```
Provide a callback that is run in the event that the Visualiser app is terminated by the user or by some other means outside of the script.

Your callback method will not take any parameters.


------
##### open
```php
public function open(string $title, int $width, int $height, int $imageCount = 1, int $posX = -1, int $posY = -1) : ?int
```
Open a new window capable of displaying the given number of images. The images are automatically laid out within a grid in the resulting window.

- **$title** A title to be displayed at the top of the window.
- **$width** The width of the window.
- **$height** The height of the window.
- **$imageCount** The exact amount of images that will be displayed within the window.
- **$posX** Starting X co-ordinate the window will be opened on.
- **$posY** Starting Y co-ordinate the window will be opened on.

**Returns:**  The unique identifier for the window. This is used to subsequently push image updates in via the `update` method.


------
##### close
```php
public function close(int $windowID) : bool
```
Close the window with the given window ID, removing it from screen and releasing the memory associated with it.

- **$windowID** The unique ID of the window that the images will be displayed in. This is obtained when the window is first created using the `open` method.


**Throws:**  InvalidArgumentException If there is no window present for the given window ID.

**Returns:**  `TRUE` on success.


------
##### info
```php
public function info(int $windowID) : ?array
```
Retrieve information about the window with the given window ID, such as dimensions, location and image count.

- **$windowID** The unique ID of the window that the images will be displayed in. This is obtained when the window is first created using the `open` method.


**Throws:**  Exception if the response does not yield the correct amount of items, or an irregular response. 
**Throws:**  InvalidArgumentException If there is no window present for the given window ID.

**Returns:**  An array containing the window width, height, x coordinate, y coordinate and image count. Will return `NULL` if no response is received.


------
##### update
```php
public function update(int $windowID, GDImage|string $image = null, array $images = null) : void
```
Push a set of updated images to the window with the given window ID. It takes either a GDImage object or an already encoded image in the form of a string (e.g. data loaded in from file or a URL).

Standard web formats should be supported such as JPEG, PNG or GIF.

The amount of images supplied should exactly match the amount of images the window was initially configured to take.

- **$windowID** The unique ID of the window that the images will be displayed in. This is obtained when the window is first created using the `open` method.
- **$image** A single image to be supplied to the window. Either a GDImage object or an already encoded string of the image data. If the $images array is also supplied then this parameter is ignored. This parameter should be used when the window has only one image.
- **$images** An array of images to be supplied to the window. The contents of which should either consist of GDImage objects or already encoded string representations. Use this parameter when the window is configured to take multiple images.


**Throws:**  InvalidArgumentException If there is no window present for the given window ID.


------
##### animate
```php
public function animate(int $width, int $height, int $frames = 0, string $title = '', int $posX = -1, int $posY = -1) 
```
Start a generator loop, with each cycle generating a new image frame to be drawn on, then rendered to an automatically created window. The image supplied is a full colour GDImage object. Upon completion of the iteration the image will be pushed to the window and displayed.

The loop will run until the frame limit is reached or the loop is broken via some other means.

- **$width** The width of the window.
- **$height** The height of the window.
- **$frames** When greater than 0, the total amount of images that will be pushed to the window before the loop exits. Omit or pass in 0 to have the loop continue indefinitely.
- **$title** A title to be displayed at the top of the window.
- **$posX** Starting X co-ordinate the window will be opened on.
- **$posY** Starting Y co-ordinate the window will be opened on.


------
