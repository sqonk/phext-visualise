import java.util.*;
import javax.swing.*;
import java.awt.*;
import java.io.*;
import java.nio.file.*;
import javax.imageio.ImageIO;
import java.awt.image.BufferedImage;
import java.util.concurrent.ConcurrentLinkedQueue;

class PHEXTVisualiser implements Runnable {
    
    static PHEXTVisualiser sharedInstance;
    
    private StringBuffer inboundData;
    private Hashtable<Integer,ImageWindow> windows;
    private int nextID = 1;
    private ConcurrentLinkedQueue<String> inputQueue;
    private Thread scanner;
    
    static protected final int NEW_WINDOW = 1;
    static protected final int CLOSE_WINDOW = 2;
    static protected final int UPDATE_IMG = 3;
    
    static protected final String TERMINATOR = "\0\0";
    static protected final String BOUNDARY = "#--0--#";
    
    static public void main(String[] args) 
    {
        sharedInstance = new PHEXTVisualiser();
        sharedInstance.startScanner(); // Start background watcher.
        sharedInstance.watchQueue();
    }
    
    public PHEXTVisualiser()
    {
        this.inboundData = new StringBuffer();
        this.windows = new Hashtable<>();
        this.inputQueue = new ConcurrentLinkedQueue<String>();
    }
    
    // process commands as they land on the queue.
    private void watchQueue()
    {
        try
        {
            while (true)
            {
                try {
                    String data = this.inputQueue.remove();
                    int command = Character.getNumericValue(data.charAt(0));
                    data = data.substring(1);
                    
                    this.dispatchInput(command, data);
                }
                catch (NoSuchElementException noItem) {
                    
                }
                
                Thread.sleep(50);
            }
        }
        catch (Exception error) {
            System.err.println("Exception occurred.");
            System.err.println(error.toString());
            error.printStackTrace();
        }
    }
    
    public void startScanner() {
        this.scanner = new Thread(this);
        this.scanner.start();
    }
    
    // Background thread picks up new events from Stdin as they arrive.
    public void run()
    {
        System.err.println("Starting watcher.");
        BufferedInputStream stdin = new BufferedInputStream(System.in, 1024*1024*10);
        int blen = 1024*1024;
        byte[] buffer = new byte[blen];
        int tlen = this.TERMINATOR.length();
        try
        {
            while (true)
            {
                if (stdin.available() > 0) 
                {
                    int upto = Math.min(blen, stdin.available());
                    int bytesRead = stdin.read(buffer, 0, upto);
                    if (bytesRead > -1) { 
                        this.inboundData.append(new String(buffer, 0, bytesRead));
                    }
            
                    int termPos = inboundData.indexOf(this.TERMINATOR);
                    if (termPos > -1) {
                        System.err.println("Got input");
                        String data = inboundData.substring(0, termPos);
                        this.inputQueue.add(data);
                    
                        // Remove the data packet from the stream.
                        this.inboundData.delete(0, termPos+tlen);
                    }
                }
                Thread.sleep(50);
            }
        }
        catch (Exception error) {
            System.err.println("Exception occurred.");
            System.err.println(error.toString());
            error.printStackTrace();
        }
    }
    
    private void dispatchInput(int command, String data) throws Exception
    {
        switch (command) {
            case NEW_WINDOW:
                this.sendOutput(this.makeWindow(data).toString());
                break;
                
            case UPDATE_IMG:
                this.updateImages(data);
                break;
                
            default:
                throw new Exception("Unknown command received: "+command);
        }
    }
    
    protected void sendOutput(String response) throws java.io.IOException
    {
        response = response + this.TERMINATOR;
        
        BufferedOutputStream os = new BufferedOutputStream(System.out);
        os.write(response.getBytes(), 0, response.length());
        os.flush();
    }
    
    protected Integer makeWindow(String data) throws Exception {
        String[] items = data.split(this.BOUNDARY);
        if (items.length != 4) {
            throw new Exception("Incorrect amount of items supplied to new window: "+items.length);
        }
        int width = Integer.parseInt(items[1]);
        int height = Integer.parseInt(items[2]);
        int imgCount = Integer.parseInt(items[3]);
        
        ImageWindow window = new ImageWindow(items[0], imgCount, this.nextID);
        window.setSize(width, height);
        window.setLocationRelativeTo(null);
        
        int rows = 1, cols = 1;
        if (imgCount > 1) {
            rows = (int)Math.sqrt(imgCount);
            cols = Math.round(imgCount / rows);  
        }
        
        window.getContentPane().setLayout(new GridLayout(rows, cols));
        
        window.prepareImageAreas();
        
        this.nextID++; // increment id ready for next window.
        
        windows.put(window.id(), window);
        
        return window.id();
    }
    
    protected int ucount = 0;
    
    protected void updateImages(String data) throws Exception { ucount++;
         String[] items = data.split(this.BOUNDARY);
         int windowID = Integer.parseInt(items[0]);
         int ack = Integer.parseInt(items[1]);
         
         Base64.Decoder decoder = Base64.getDecoder();  
         Vector<Image> images = new Vector<Image>();
         
         for (int i = 2; i < items.length; i++)
         {
             byte[] imgData = decoder.decode(items[i]);
             Image img = ImageIO.read(new ByteArrayInputStream(imgData));
             images.add(img);
         }
         
         ImageWindow win = this.windows.get(new Integer(windowID));
         win.updateImages(images);
         System.err.println(ucount);
         if (ack != 0) {
             System.err.println("Keyframe reply");
             this.sendOutput("1");
         }
    }
    
    class ImageWindow extends JFrame
    {
        protected String title;
        protected Integer id;
        
        protected Image[] images; 
        protected Vector<ImageCanvas> canvases;
        
        public ImageWindow(String title, int imageCount, int id)
        {
            this.title = title;
            this.id = new Integer(id);
            this.images = new Image[imageCount];
            this.canvases = new Vector<ImageCanvas>();

    		setTitle(title);  
    		setResizable(true);
            setVisible(true);
            setLocationRelativeTo(null);
        }
        
        public void prepareImageAreas() 
        {
            Dimension windowD = this.getSize();
            int imageCount = images.length;
            for (int i = 0; i < imageCount; i++)
            {
                ImageCanvas c = new ImageCanvas();
                this.getContentPane().add("Center", c);
                this.canvases.add(c);
            }
        }
        
        public String title() {
            return this.title;
        }
        
        public Integer id() {
            return this.id;
        }
        
        public void updateImages(Vector<Image> images) {
            int limit = Math.min(images.size(), this.canvases.size());
            
            for (int i = 0; i < limit; i++) {
                this.canvases.get(i).setImage(images.get(i));
                this.canvases.get(i).repaint();
            }
        }
    }
    
    class ImageCanvas extends Canvas {
        private Image image = null;
        
        public ImageCanvas() {
            
        }
        
        public void setImage(Image image) {
            this.image = image;
        }
        
    	public void update(Graphics g) {
    		paint(g);
    	}
        
        public void paint(Graphics g) {
            if (this.image != null)
            {
                Dimension scaled = this.aspectFit(this.image.getWidth(null), this.image.getHeight(null));
                Dimension bounds = this.getSize();

                int shortestContainerSide = Math.min(bounds.width, bounds.height);
                int x = (bounds.width / 2) - (scaled.width / 2);
                int y = (bounds.height / 2) - (scaled.height / 2);
                
                g.drawImage(this.image, x, y, scaled.width, scaled.height, null);  
            }
        }
        
        private Dimension scaled(int w, int h, int max) { 
            double p = Math.min((double)w/h, (double)h/w);
            int shortestSide = (int)(max * p);
            
            int width, height;
            if (w > h) {
                width = max;
                height = shortestSide;
            }
            else {
                width = shortestSide;
                height = max;
            }
            return new Dimension(width, height);
        }
        
        // Aspect ratio scaling for the image.
        private Dimension aspectFit(int width, int height) {
            Dimension container = this.getSize();
            
            if (width > container.width || height > container.height)
            {
                Dimension im = new Dimension(width, height);
                Dimension scaled;
                
                if (im.width > im.height) {
                    // landscape image
                    scaled = this.scaled(width, height, container.width);
                }
                else {
                    // portrait image
                    scaled = this.scaled(width, height, container.height);
                }  
                
                return scaled;        
            }
            
            return new Dimension(width, height);
        }
    }
}