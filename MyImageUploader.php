<?php

namespace App\Libs;

use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Facades\Storage;

class MyImageUploader
{
    public $options;
    public $file;

    public function __construct( $options = array() )
    {
        $this->options = array(
            'upload_dir' => storage_path('app/temp'),
            'versions' => [
                    'large' => ['w'=>1280, 'h'=>800, 'mode'=>'r'],
                    'small' => ['w'=>640, 'h'=>480, 'q'=>80, 'mode'=>'f'],
                    'thumb' => ['w'=>150, 'h'=>150, 'q'=>60, 'mode'=>'f'],
                ],
            'file' => true,
            'input' => 'image'
        );

        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
        }

        if( $this->options['file'] )
            $this->file = request()->file( $this->options['input'] );
        else
            $this->file = request()->input( $this->options['input'] );
    }

    public function uploadFile()
    {
        $file_name;

        if( is_array($this->file) ) // multiple upload
         {
            $file_name = array();

            foreach ($this->file as $file) {
                array_push($file_name, $this->_uploadFile( $file ));
            }
        }
        else    // single upload
        {
            $file_name = $this->_uploadFile( $this->file );
        }

        return $file_name;
    }

    public function _uploadFile($file)
    {
        $stored_org_file_name = $this->_storeOriginalFile_and_getFileName($file);

        foreach ($this->options['versions'] as $version => $options) {
            $w = $options['w'] ?? null;
            $h = $options['h'] ?? null;
            $q = $options['q'] ?? 100;
            $options['mode'] = $options['mode'] ?? 'f';

            $vrsn_dir = $this->options['upload_dir'].$version.'/';

            if(! Storage::exists( $vrsn_dir ) )
                Storage::makeDirectory( $vrsn_dir );

            //make Image Versions
            $img = Image::make( storage_path('app/'.$this->options['upload_dir']) . $stored_org_file_name);
            if( $w && $h )
            {
                switch ($options['mode']) {
                    case 'r':
                        $w = $img->width() < $w ? $img->width() : $w;
                        $h = $img->height() < $h ? $img->height() : $h;

                        $img->resize($w, $h, function($constraint){
                            $constraint->aspectRatio();
                        } );
                        break;
                    case 'f':
                        $img->fit($w, $h);
                        break;
                }
            }
            else if($w)
                $img->widen($w);
            else
                $img->heighten($h);

            $img->save( storage_path('app/'.$vrsn_dir) . $stored_org_file_name, $q );
        }

        // clear original uploaded file
        Storage::delete( $this->options['upload_dir'].$stored_org_file_name );

        return $stored_org_file_name;
    }

    protected function _storeOriginalFile_and_getFileName($file)
    {
        $stored_org_file_name;

        if( $this->options['file'] )
        {
            $stored_org_file_name = md5(microtime()).'.'.$file->getClientOriginalExtension();
            $file->storeAs( $this->options['upload_dir'], $stored_org_file_name );
        }
        else
        {
            $img = Image::make( $file );
            $stored_org_file_name = md5(microtime()).'.'.$this->_mimeTypeFileExtension( $img->mime() );
            $img->save( storage_path('app/'.$this->options['upload_dir']).$stored_org_file_name );
        }

        return $stored_org_file_name;
    }

    protected static function _mimeTypeFileExtension($mime_type)
    {
        switch ($mime_type) {
            case 'image/jpeg':
                return 'jpg';
            case 'image/png':
                return 'png';
            case 'image/gif':
                return 'gif';
            case 'image/bmp':
                return 'bmp';
            default:
                return 'jpg';
        }
    }
    public function destroyFile($filename)
    {
        foreach ($this->options['versions'] as $version => $options) {
            $file = $this->options['upload_dir'].$version.'/'.$filename;

            if( $filename !== null && is_file( storage_path('app/'.$file)) )
            {
                Storage::delete([ $file ]);
            }
        }
    }
}
