<?php 
namespace App\Service;

use App\MisClases\FacturaPdfToJsonService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\String\Slugger\SluggerInterface;

class FacturaProcessorService
{
    public function __construct(
        private KernelInterface $kernel,
        private FacturaPdfToJsonService $iaService,
        private SluggerInterface $slugger
    ) {}

    public function procesarFacturaDesdeRequest(Request $request): array
    {
        $usarImagen = $request->request->get('usar_imagen') === '1';

        /** @var UploadedFile|null $pdfFile */
        $pdfFile = $request->files->get('factura');
        if (!$pdfFile || !$pdfFile->isValid()) {
            throw new \RuntimeException('Archivo no válido');
        }

        $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $pdfFilename = $safeFilename . '-' . uniqid() . '.' . $pdfFile->guessExtension();

        $rutaDestino = $this->kernel->getProjectDir() . '/public_html/var/facturas/' . $pdfFilename;

        // Mover el archivo
        $pdfFile->move(dirname($rutaDestino), basename($rutaDestino));

        // Si hay que procesar como imagen
        if ($usarImagen) {
            $outputImagePath = str_replace('.pdf', '.jpg', $rutaDestino);

            $process = new Process([
                'convert',
                '-density', '300',
                '-colorspace', 'gray',
                '-sharpen', '0x1.0',
                '-trim',
                '+repage',
                $rutaDestino,
                '-background', 'white',
                '-alpha', 'remove',
                '-quality', '100',
                $outputImagePath
            ]);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $datos = $this->iaService->procesarFacturaPdf($outputImagePath);
        } else {
            $datos = $this->iaService->procesarFacturaPdf($rutaDestino);
        }

        return [$pdfFilename, $datos];
    }
}
?>