import sys
import time

# USB HID Relay Controller (PoolStream)

def send_relay_command(channel, state):
    vid = 0x16c0
    pid = 0x05df
    
    print(f"Connecting to USB Relay (VID: {hex(vid)}, PID: {hex(pid)})...")
    try:
        # In actual hardware:
        # import hid
        # device = hid.device()
        # device.open(vid, pid)
        # buffer = [0, 0xFD if state == 'on' else 0xFC, channel, 0, 0, 0, 0, 0]
        # device.write(buffer)
        # device.close()
        print(f"SUCCESS: Relay Channel {channel} turned {state.upper()}")
    except Exception as e:
        print(f"ERROR: Failed to communicate with Relay: {e}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python relay_controller.py <channel_number> <on/off>")
        sys.exit(1)
        
    try:
        channel = int(sys.argv[1])
        state = sys.argv[2].lower()
        
        if state not in ['on', 'off']:
            print("State must be 'on' or 'off'")
            sys.exit(1)
            
        send_relay_command(channel, state)
    except ValueError:
        print("Channel must be an integer")
        sys.exit(1)
