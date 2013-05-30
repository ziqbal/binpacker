<?

// Require class file
require_once("class-bp.php");

// Create Bin Packer Object
// You can ignore argument if you don't want verbose output
$myobj=new BinPacker(true);

// Change default Bin Size (which is 690M) to another size
// First argument is a number and the second a unit
// Unit can be (G)igabyte, (M)egabyte, (K)ilobyte or (B)yte
$myobj->setBinSize(4.7,"g");

// Add all files with 'mpg' extensions in folder, includeing subfolders
// You can ignore extension argument which will then include every file
// The 'True' argument can be ignored if you don't want to recurse into
// directories
$myobj->add('C:\\input\\bigfiles\\monday',true,'mpg');

// Add all files with 'avi' extension in directory, including sub directories
// Just to show that the case of extension string argument does not matter
$myobj->add('/input/bigfiles/tuesday',true,'AVI');

// Add all files with 'zip' extension in one directory only
// No recursion here
$myobj->add('/input/downloads',false,'zip');

// Add all files in single directory - do not recurse
$myobj->add('/input/dodgy');


// Add specific file
$myobj->add('/tmp/abcdef.mp3');

// Pack everything
$myobj->pack();

// Move everything to output directory
// Ignoring argument will cause a COPY operation
// Method also creates txt files which give information about what files are
// contained in each bin
$myobj->output("C:\\output",true);

// Thats it folks! Check your output directory!
// Class has auto destructor... which doesn't do much at the moment.

?>
