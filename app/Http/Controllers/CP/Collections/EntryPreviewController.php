<?php

namespace Statamic\Http\Controllers\CP\Collections;

use Exception;
use Throwable;
use Statamic\API\Site;
use Statamic\API\Entry;
use Statamic\API\Blueprint;
use Illuminate\Http\Request;
use Statamic\API\Collection;
use Illuminate\Support\Facades\Facade;
use Statamic\Http\Controllers\CP\CpController;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class EntryPreviewController extends CpController
{
    public function show()
    {
        return view('statamic::entries.preview');
    }

    public function edit(Request $request, $collection, $entry)
    {
        $this->authorize('preview', $entry);

        $fields = $entry->blueprint()
            ->fields()
            ->addValues($request->input('preview', []))
            ->process();

        foreach (array_except($fields->values(), ['slug']) as $key => $value) {
            $entry->setSupplement($key, $value);
        }

        return $this->getEntryResponse($request, $entry)->getContent();
    }

    public function create(Request $request, $collection, $site)
    {
        $fields = Blueprint::find($request->blueprint)
            ->fields()
            ->addValues($preview = $request->preview)
            ->process();

        $values = array_except($fields->values(), ['slug']);

        $entry = Entry::create()
            ->collection($collection)
            ->in($site->handle(), function ($localized) use ($values, $preview) {
                $localized
                    ->slug($preview['slug'] ?? 'slug')
                    ->data($values);
            });

        if ($collection->order() === 'date') {
            $entry->order($preview['date'] ?? now()->format('Y-m-d-Hi'));
        }

        return $this->getEntryResponse($request, $entry)->getContent();
    }

    protected function getEntryResponse($request, $entry)
    {
        $url = $request->amp ? $entry->ampUrl() : $entry->absoluteUrl();

        $subrequest = Request::createFromBase(SymfonyRequest::create($url));

        app()->instance('request', $subrequest);
        Facade::clearResolvedInstance('request');

        try {
            $response = $entry->toLivePreviewResponse($subrequest, $request->extras);
        } catch (Exception $e) {
            app(ExceptionHandler::class)->report($e);
            $response = app(ExceptionHandler::class)->render($subrequest, $e);
        } catch (Throwable $e) {
            app(ExceptionHandler::class)->report($e = new FatalThrowableError($e));
            $response = app(ExceptionHandler::class)->render($subrequest, $e);
        }

        app()->instance('request', $request);
        Facade::clearResolvedInstance('request');

        return $response;
    }
}
