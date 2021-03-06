<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:41 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Framing;
use Devristo\Phpws\Exceptions\WebSocketFrameSizeMismatch;

/**
 * HYBIE WebSocketFrame
 *
 * @author Chris
 *
 */
class WebSocketFrame implements WebSocketFrameInterface
{

    // First Byte
    protected $FIN = 0;
    protected $RSV1 = 0;
    protected $RSV2 = 0;
    protected $RSV3 = 0;
    protected $opcode = WebSocketOpcode::TextFrame;
    // Second Byte
    protected $mask = 0;
    protected $payloadLength = 0;
    protected $maskingKey = 0;
    protected $payloadData = '';
    protected $actualLength = 0;

    private function __construct()
    {

    }

    public static function create($type, $data = null)
    {
        $o = new self();

        $o->FIN = true;
        $o->payloadData = $data;
        $o->payloadLength = $data != null ? strlen($data) : 0;
        $o->setType($type);

        return $o;
    }

    public function setMasked($mask)
    {
        $this->mask = $mask ? 1 : 0;
    }

    public function isMasked()
    {
        return $this->mask == 1;
    }

    protected function setType($type)
    {
        $this->opcode = $type;

        if ($type == WebSocketOpcode::CloseFrame)
            $this->mask = 1;
    }

    protected static function IsBitSet($byte, $pos)
    {
        return ($byte & pow(2, $pos)) > 0 ? 1 : 0;
    }

    protected static function rotMask($data, $key, $offset = 0)
    {
        // Rotate key for example if $offset=1 and $key=abcd then output will be bcda
        $rotated_key = substr($key, $offset) . substr($key, 0, $offset);

        // Repeat key until it is at least the size of the $data
        $key_pad = str_repeat($rotated_key, ceil(1.0*strlen($data) / strlen($key)));

        return $data ^ substr($key_pad, 0, strlen($data));
    }

    public function getType()
    {
        return $this->opcode;
    }

    public function encode()
    {
        $this->payloadLength = strlen($this->payloadData);

        $firstByte = $this->opcode;

        $firstByte += $this->FIN * 128 + $this->RSV1 * 64 + $this->RSV2 * 32 + $this->RSV3 * 16;

        $encoded = chr($firstByte);

        if ($this->payloadLength <= 125) {
            $secondByte = $this->payloadLength;
            $secondByte += $this->mask * 128;

            $encoded .= chr($secondByte);
        } else if ($this->payloadLength <= 255 * 255 - 1) {
            $secondByte = 126;
            $secondByte += $this->mask * 128;

            $encoded .= chr($secondByte) . pack("n", $this->payloadLength);
        } else {
            // TODO: max length is now 32 bits instead of 64 !!!!!
            $secondByte = 127;
            $secondByte += $this->mask * 128;

            $encoded .= chr($secondByte);
            $encoded .= pack("N", 0);
            $encoded .= pack("N", $this->payloadLength);
        }

        $key = 0;
        if ($this->mask) {
            $key = pack("N", rand(0, pow(255, 4) - 1));
            $encoded .= $key;
        }

        if ($this->payloadData)
            $encoded .= ($this->mask == 1) ? $this->rotMask($this->payloadData, $key) : $this->payloadData;

        return $encoded;
    }

    public static function decode(&$raw, $head = null)
    {
        if ($head != null) {
            $frame = $head;
        } else {
            $frame = new self();

            // Read the first two bytes, then chop them off
            list($firstByte, $secondByte) = substr($raw, 0, 2);
            $raw = substr($raw, 2);

            $firstByte = ord($firstByte);
            $secondByte = ord($secondByte);

            $frame->FIN = self::IsBitSet($firstByte, 7);
            $frame->RSV1 = self::IsBitSet($firstByte, 6);
            $frame->RSV2 = self::IsBitSet($firstByte, 5);
            $frame->RSV3 = self::IsBitSet($firstByte, 4);

            $frame->mask = self::IsBitSet($secondByte, 7);

            $frame->opcode = ($firstByte & 0x0F);

            $len = $secondByte & ~128;

            if ($len <= 125)
                $frame->payloadLength = $len;
            elseif ($len == 126) {
                $arr = unpack("nfirst", $raw);
                $frame->payloadLength = array_pop($arr);
                $raw = substr($raw, 2);
            } elseif ($len == 127) {
                list(, $h, $l) = unpack('N2', $raw);
                $frame->payloadLength = ($l + ($h * 0x0100000000));
                $raw = substr($raw, 8);
            }

            if ($frame->mask) {
                $frame->maskingKey = substr($raw, 0, 4);
                $raw = substr($raw, 4);
            }
        }

        $currentOffset = $frame->actualLength;
        $fullLength = min($frame->payloadLength - $frame->actualLength, strlen($raw));
        $frame->actualLength += $fullLength;

        if ($fullLength < strlen($raw)) {
            $frameData = substr($raw, 0, $fullLength);
            $raw = substr($raw, $fullLength);
        } else {
            $frameData = $raw;
            $raw = '';
        }

        if ($frame->mask)
            $frame->payloadData .= self::rotMask($frameData, $frame->maskingKey, $currentOffset);
        else
            $frame->payloadData .= $frameData;

        return $frame;
    }

    public function isReady()
    {
        if ($this->actualLength > $this->payloadLength) {
            throw new WebSocketFrameSizeMismatch($this);
        }
        return ($this->actualLength == $this->payloadLength);
    }

    public function isFinal()
    {
        return $this->FIN == 1;
    }

    public function getData()
    {
        return $this->payloadData;
    }

}