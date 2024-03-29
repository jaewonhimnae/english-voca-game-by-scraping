<?php
class FileCache
{
    
    /**
     * The root cache directory.
     * @var string
     */
    protected $_cache_path = '/tmp/cache';
    /** @var string */
    protected $file_extension = '';
    /**
     * Creates a FileCache object
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
        $available_options = array('_cache_path', 'file_extension');
        foreach ($available_options as $name) {
            if (isset($options[$name])) {
                $this->$name = $options[$name];
            }
        }
    }
    /**
     * Fetches an entry from the cache.
     *
     * @param string $id
     */
	public function get($id)
	{
		$data = $this->_get($id);
		return is_array($data) ? $data['data'] : FALSE;
	}
    protected function get_cache_file_path($id){
        $id = str_replace(DIRECTORY_SEPARATOR, '/', $id);
        $add_separator = '';
        $add_file_extension = '';
        if( strpos('/', $id) !== 0 ){
            $add_separator = '/';
        }
        
        $ext = pathinfo($id, PATHINFO_EXTENSION);
        
        if( !(isset($ext['extension']) && $ext['extension']) ){
            $add_file_extension = $this->file_extension;
        }
        return $this->_cache_path.$add_separator.$id.$add_file_extension;
    }
	protected function _get($id)
	{
        $cache_file_path = $this->get_cache_file_path($id);
		if ( ! is_file($cache_file_path) )
		{
			return FALSE;
		}
		$data = unserialize(file_get_contents( $cache_file_path ));
		if ($data['ttl'] > 0 && time() > $data['time'] + $data['ttl'])
		{
			unlink( $cache_file_path );
			return FALSE;
		}
		return $data;
	}
	public function write_file($path, $data, $mode = 'wb')
	{
		if ( ! $fp = @fopen($path, $mode))
		{
			return FALSE;
		}
		flock($fp, LOCK_EX);
		for ($result = $written = 0, $length = strlen($data); $written < $length; $written += $result)
		{
			if (($result = fwrite($fp, substr($data, $written))) === FALSE)
			{
				break;
			}
		}
		flock($fp, LOCK_UN);
		fclose($fp);
		return is_int($result);
	}
    /**
     * Deletes a cache entry.
     *
     * @param string $id
     *
     * @return bool
     */
    public function delete($id)
    {
        $cache_file_path = $this->get_cache_file_path($id);
        return file_exists($cache_file_path) ? unlink($cache_file_path) : FALSE;
    }
    /**
     * Puts data into the cache.
     *
     * @param string $id
     * @param mixed  $data
     * @param int    $lifetime
     *
     * @return bool
     */
	public function save($id, $data, $ttl = 60, $raw = FALSE)
	{
        $cache_file_path = $this->get_cache_file_path($id);
		$contents = array(
			'time'		=> time(),
			'ttl'		=> $ttl,
			'data'		=> $data
		);
		if ($this->write_file($cache_file_path, serialize($contents)))
		{
			chmod($cache_file_path, 0644);
			return TRUE;
		}
		return FALSE;
	}
    /**
     * Fetches a base directory to store the cache data
     *
     * @return string
     */
    protected function getCacheDirectory()
    {
        return $this->_cache_path;
    }
    /**
     * Encodes some data to a string so it can be written to disk
     *
     * @param mixed $data
     * @param int $ttl
     * @return string
     */
    public function encode($data, $ttl)
    {
        $expire = null;
        if ($ttl !== null) {
            $expire = time() + $ttl;
        }
        return serialize(array($data, $expire));
    }
    /**
     * Decodes a string encoded by {@see encode()}
     *
     * Must returns a tuple (data, expire). Expire
     * can be null to signal no expiration.
     *
     * @param string $data
     * @return array (data, expire)
     */
    public function decode($data)
    {
        return unserialize($data);
    }
}
?>