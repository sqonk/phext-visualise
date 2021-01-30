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

import java.util.*;
import javax.swing.*;
import java.awt.*;
import java.io.*;
import java.nio.file.*;
import java.nio.ByteBuffer;
import javax.imageio.ImageIO;
import java.util.concurrent.ConcurrentLinkedQueue;

class PHEXTVisualiser implements Runnable {
    
    static PHEXTVisualiser sharedInstance;
    
    private ByteArrayOutputStream inboundData;
    private Hashtable<Integer,ImageWindow> windows;
    private int nextID = 1;
    private ConcurrentLinkedQueue<ByteBuffer> inputQueue;
    private Thread scanner;
    
    static protected final int NEW_WINDOW = 1;
    static protected final int CLOSE_WINDOW = 2;
    static protected final int UPDATE_IMG = 3;
        
    static public void main(String[] args) 
    {
        sharedInstance = new PHEXTVisualiser();
        sharedInstance.startScanner(); // Start background watcher.
        sharedInstance.watchQueue();
    }
    
    public PHEXTVisualiser()
    {
        this.inboundData = new ByteArrayOutputStream();
        this.windows = new Hashtable<>();
        this.inputQueue = new ConcurrentLinkedQueue<ByteBuffer>();
    }
    
    // process commands as they land on the queue.
    private void watchQueue()
    {
        try
        {
            while (true)
            {
                try {
                    ByteBuffer data = this.inputQueue.remove();
                    int command = data.getInt();
                    
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
        BufferedInputStream stdin = new BufferedInputStream(System.in);
        int blen = 1024*1024;
        byte[] buffer = new byte[blen];
        
        try
        {
            while (true)
            {
                int totalRead = 0;
                while (stdin.available() > 0) 
                {
                    int upto = Math.min(blen, stdin.available());
                    int bytesRead = stdin.read(buffer, 0, upto);
                    if (bytesRead > -1) { 
                        
                        this.inboundData.write(buffer, 0, bytesRead);
                        totalRead += bytesRead;
                    }
                }
                            
                if (totalRead > 0) {
                    ByteBuffer packet = ByteBuffer.wrap(this.inboundData.toByteArray());
                    int command = packet.getInt();
                    packet.rewind();
                    this.inputQueue.add(packet);
                
                    // Remove the data packet from the stream.
                    this.inboundData = new ByteArrayOutputStream();
                    
                    if (command == UPDATE_IMG)
                        this.sendAck();
                }
                else
                    Thread.sleep(10);
            }
        }
        catch (Exception error) {
            System.err.println("Exception occurred.");
            System.err.println(error.toString());
            error.printStackTrace();
        }
    }
    
    private void dispatchInput(int command, ByteBuffer data) throws Exception
    {
        switch (command) {
            case NEW_WINDOW:
                this.makeWindow(data);
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
        BufferedOutputStream os = new BufferedOutputStream(System.out);
        os.write(response.getBytes(), 0, response.length());
        os.flush();
    }
    
    protected void sendAck() throws java.io.IOException {  
        System.out.print(true);
        System.out.flush();
    }
    
    protected void makeWindow(ByteBuffer data) throws Exception {
        int width = data.getInt();
        int height = data.getInt();
        int imgCount = data.getInt();
        int posX = data.getInt();
        int posY = data.getInt();
        
        int titleLen = data.getInt();
        byte[] tbuf = new byte[titleLen];
        data.get(tbuf, 0, titleLen);
        String title = new String(tbuf);
        
        ImageWindow window = new ImageWindow(title, imgCount, this.nextID);
        window.setSize(width, height);
        if (posX < 0 || posY < 0)
            window.setLocationRelativeTo(null);
        else
            window.setLocation(posX, posY);
        
        int rows = 1, cols = 1;
        if (imgCount > 1) {
            rows = (int)Math.sqrt(imgCount);
            cols = Math.round(imgCount / rows);  
        }
        
        window.getContentPane().setLayout(new GridLayout(rows, cols));
        
        window.prepareImageAreas();
        
        this.nextID++; // increment id ready for next window.
        
        windows.put(window.id(), window);
        
        this.sendOutput(window.id().toString());
    }
    
    protected int ucount = 0;
    
    protected void updateImages(ByteBuffer data) throws Exception { ucount++;
        int windowID = data.getInt();
         
        Vector<Image> images = new Vector<Image>();
         
        while (data.remaining() > 0)
        {
            int length = data.getInt(); 
            byte[] imgData = new byte[length];
            data.get(imgData, 0, length); 
            images.add(ImageIO.read(new ByteArrayInputStream(imgData)));
        }
         
        ImageWindow win = this.windows.get(new Integer(windowID));
        win.updateImages(images);
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
            
            Vector<ImageCanvas> canvases = this.canvases;
            
            for (int i = 0; i < limit; i++) {
                canvases.get(i).setImage(images.get(i));
            }
            
            SwingUtilities.invokeLater(new Runnable() {
                public void run() {
                    for (int i = 0; i < limit; i++)
                        canvases.get(i).repaint();
                }
            });
            
        }
    }
    
    class ImageCanvas extends Canvas {
        private Image image = null;
        
        public ImageCanvas() {
            
        }
        
        public void setImage(Image image) {
            if (this.image != null)
                this.image.flush();
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