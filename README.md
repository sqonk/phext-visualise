# PHEXT Visualise

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-8892BF.svg)](https://php.net/) [![Minimum PHP Version](https://img.shields.io/badge/java-%3E%3D%208-8892BF.svg)](https://adoptopenjdk.net) [![License](https://sqonk.com/opensource/license.svg)](license.txt)

Visualise is a cross platform *non-interactive* UI for PHP over the command line SAPI. It displays rendered images in a window with the ability for real-time updates.

Image frames can either be constructed with GD and passed to Visualise directly or produced or acquired via other means (e.g. from file) by providing the pre-rendered image data as a string.

It uses the Java platform run under a sub-process that binds each window to the parent PHP script using the PCNTL extension.

### Philosophy

When attempting to build simple scripts or a proof of concept the inclination is to keep any output text-based and within the confines of the command line. While this avoids a lot of extra bulk and work necessary to make a program function with a graphical UI, it also restricts the type of information that can be displayed to the user (usually the developer writing the code).

Desktop graphical user interfaces typically come with event loops and other control frameworks that force (through necessity) that you structure your code to fit within their system.

Likewise Web apps, PHP's primary domain of usage, run over request-response cycles that spawn new running instances of a script with every run. 

Maybe you're trialing a proof-of-concept idea that you would like to get up and running quickly, or you have simple requirements for a command line script but would like graphical updates to be displayed onscreen without having to vastly change your logic. Visualise can solve this problem by fitting in with your code instead of the other way round.

### Why Java?

Native PHP extensions have a nasty habit of breaking with nearly every major release of the language. For the various extensions that supported GUI bindings - as soon as the maintainers lost interest or otherwise moved on, compatibility was lost.

Java has been around since the 90s, widely used in enterprise and runs reliably on OS X, Linux and Windows - it's not going anyway soon.

The Java side of Visualise intentionally uses as few classes as possible, requires no 3rd party Java libraries and makes use of tools that have been present in the language for most of its life, such as Swing UI.

Even if I were to disappear off the face of the earth tomorrow then the library should continue to operate reliably for the forceable future.

