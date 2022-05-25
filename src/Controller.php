<?php

namespace Tiger;

use Exception;
use RudyMas\Manipulator\Text;
use RudyMas\XML_JSON;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

/**
 * Class Controller (PHP version 7.4)
 *
 * @author      Rudy Mas <rudy.mas@rmsoft.be>
 * @copyright   2022, rmsoft.be. (https://www.rmsoft.be/)
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version     7.4.1.0
 * @package     Tiger
 */
class Controller
{
    /**
     * @param null|string $page
     * @param array $data
     * @param string $type
     * @param int $httpResponseCode
     * @param bool $debug
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function render(
        ?string $page,
        array $data,
        string $type,
        int $httpResponseCode = 200,
        bool $debug = false
    ): void {
        switch (strtoupper($type)) {
            case 'HTML':
                $this->renderHTML($page);
                break;
            case 'JSON':
                $this->renderJSON($data, $httpResponseCode);
                break;
            case 'XML':
                $this->renderXML($data, $httpResponseCode);
                break;
            case 'PHP':
                $this->renderPHP($page, $data);
                break;
            case 'TWIG':
                $this->renderTWIG($page, $data, $debug);
                break;
            default:
                throw new Exception("<p><b>Exception:</b> Wrong page type ({$type}) given.</p>", 501);
        }
        ob_flush();
        flush();
    }

    /**
     * @param string $page Page to redirect to (Can be an URL or a routing directive)
     */
    public function redirect(string $page): void
    {
        if (preg_match("/(http|ftp|https)?:?\/\//", $page)) {
            header('Location: ' . $page);
        } else {
            $dirname = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
            header('Location: ' . $dirname . $page);
        }
        exit;
    }

    /**
     * @param string $page HTML page to output to the browser
     */
    private function renderHTML(string $page): void
    {
        $display = $_SERVER['DOCUMENT_ROOT'] . BASE_URL . '/src/Views/' . $page;
        if (file_exists($display)) {
            readfile($display);
        } else {
            header('HTTP/1.1 404 Not Found');
        }
    }

    /**
     * @param array $data Array of data following XML standards
     * @param int $httpResponseCode HTTP response code to send (Default: 200)
     */
    private function renderJSON(array $data, int $httpResponseCode = 200): void
    {
        if ($httpResponseCode >= 200 && $httpResponseCode <= 299) {
            $jsonData = $data;
        } else {
            $jsonData['error']['code'] = $httpResponseCode;
            $jsonData['error']['message'] = 'Error ' . $httpResponseCode . ' has occurred';
        }

        $convert = new XML_JSON();
        $convert->setArrayData($jsonData);
        $convert->array2json();

        http_response_code($httpResponseCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: *');
        print($convert->getJsonData());
    }

    /**
     * @param array $data Array of data following XML standards
     * @param int $httpResponseCode HTTP response code to send (Default: 200)
     */
    private function renderJSONData(array $data, int $httpResponseCode = 200): void
    {
        if ($httpResponseCode >= 200 && $httpResponseCode <= 206) {
            $jsonData['data'] = $data;
        } else {
            $jsonData['error']['code'] = $httpResponseCode;
            $jsonData['error']['message'] = 'Error ' . $httpResponseCode . ' has occurred';
        }

        $convert = new XML_JSON();
        $convert->setArrayData($jsonData);
        $convert->array2json();

        http_response_code($httpResponseCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: *');
        print($convert->getJsonData());
    }

    /**
     * @param array $data Array of data following XML standards
     * @param int $httpResponseCode HTTP response code to send (Default: 200)
     */
    private function renderXML(array $data, int $httpResponseCode = 200): void
    {
        if ($httpResponseCode >= 200 && $httpResponseCode <= 206) {
            $xmlData = $data;
        } else {
            $xmlData['error']['code'] = $httpResponseCode;
            $xmlData['error']['message'] = 'Error ' . $httpResponseCode . ' has occurred';
        }

        $convert = new XML_JSON();
        $convert->setArrayData($xmlData);
        $convert->array2xml('members');

        http_response_code($httpResponseCode);
        header('Content-Type: application/xml; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: *');
        print($convert->getXmlData());
    }

    /**
     * @param string $page Name of the HTML5 view class
     * @param array $data array of data to insert on the page
     */
    private function renderPHP(string $page, array $data): void
    {
        list($view, $subpage) = $this->processPhpPage($page);
        if ($subpage == null) {
            new $view($data);
        } else {
            $subpage .= 'Page';
            $loadPage = new $view($data);
            $loadPage->$subpage();
        }
    }

    /**
     * @param string $page
     * @return array
     */
    private function processPhpPage(string $page): array
    {
        $view = '\\Views';
        $split = explode(':', trim($page, '/'));
        if (count($split) > 1) {
            $subpage = $split[1];
        } else {
            $subpage = null;
        }
        $class = explode('/', trim($split[0], '/'));
        foreach ($class as $path) {
            $view .= "\\{$path}";
        }
        return [$view, $subpage];
    }

    /**
     * @param string $page
     * @param array $data
     * @param bool $debug
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function renderTWIG(string $page, array $data, bool $debug = false): void
    {
        $loader = new FilesystemLoader('src/Views');
        $twig = new Environment($loader, ['debug' => $debug]);
        if ($debug === true) {
            $twig->addExtension(new DebugExtension());
        }
        $twig->display($page, $data);
    }

    /**
     * Encode a file into base64
     *
     * @param string $file
     *
     * @return string
     */
    protected function encodeBase64(string $file): string
    {
        $type = @mime_content_type($file);
        $base64 = base64_encode(@file_get_contents($file));
        if (!empty($base64)) {
            $document = 'data:' . $type . ';base64,' . $base64;
        } else {
            $document = 'File not found!';
        }
        return $document;
    }

    /**
     * Turn Base64 string into a specific file and save it
     *
     * @param string $imageData
     * @param string $folder
     * @return string
     */
    protected function decodeBase64AndSaveImage(string $imageData, string $folder = 'api_upload'): string
    {
        $Text = new Text();
        $filename = $Text->randomText(30);

        if (!is_dir(SYSTEM_ROOT . '/public/img/' . $folder)) {
            mkdir(SYSTEM_ROOT . '/public/img/' . $folder, 0775, true);
        }

        $image = explode(',', $imageData);
        switch (true) {
            case (strpos('image/png', $image[0])):
                $file = $folder . '/' . $filename . '.png';
                break;
            case (strpos('image/jpg', $image[0])):
            case (strpos('image/jpeg', $image[0])):
                $file = $folder . '/' . $filename . '.jpg';
                break;
            case (strpos('image/gif', $image[0])):
                $file = $folder . '/' . $filename . '.gif';
                break;
            case (strpos('image/webp', $image[0])):
                $file = $folder . '/' . $filename . '.webp';
                break;
            default:
                $file = $folder . '/' . $filename . '.jpg';
        }

        $newFile = fopen(SYSTEM_ROOT . '/public/img/' . $file, "wb");
        fwrite($newFile, base64_decode($image[1]));
        fclose($newFile);

        return $file;
    }

    /**
     * @param mixed $array
     * @param bool $stop
     */
    public function checkArray($array, bool $stop = false): void
    {
        print('<pre>');
        print_r($array);
        print('</pre>');
        if ($stop) {
            exit;
        }
    }
}
