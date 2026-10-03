<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\SaveDraftRequest;
use App\Http\Requests\SubmitLoanApplicationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Jobs\RunStopFactorChecks;
use App\Models\LoanApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * КОНТРОЛЛЕР заявок.
 *
 * Аналог в Битриксе: Bitrix\Main\Engine\Controller с методами ...Action() или class.php компонента.
 *
 * Обрати внимание на параметры методов — их подставляет сам Laravel по типу:
 *   Request / SaveDraftRequest  → текущий HTTP-запрос (второй ещё и провалидирован);
 *   LoanApplication $loanApplication → модель, найденная по id из URL. Это ROUTE MODEL BINDING:
 *       в маршруте /loan-applications/{loan_application} лежит число, а в метод приходит объект.
 *       Не нашлось (или заявка мягко удалена) — Laravel сам вернёт 404, до метода дело не дойдёт.
 *
 * Контроллер тонкий: принять запрос, проверить права, вызвать модель/сервис, отдать ресурс.
 */
class LoanApplicationController extends Controller
{
    /**
     * GET /api/loan-applications?status=draft&page=2 — список СВОИХ заявок.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // enum() достаёт параметр и сразу превращает его в enum. Нет параметра или мусор — будет null.
        $status = $request->enum('status', ApplicationStatus::class);

        $applications = $request->user()
            ->loanApplications() // построитель запроса: WHERE user_id = <id текущего пользователя>
            // EAGER LOADING. Без with() ресурс не отдал бы stop_factors вовсе, а при обращении
            // к $application->stopFactorResults в цикле было бы по запросу на каждую заявку — тот самый N+1.
            // С with() запросов всегда два: заявки + все их результаты через WHERE ... IN (...).
            ->with('stopFactorResults')
            // when(): добавить условие, только если $status не null
            ->when($status, fn ($query) => $query->withStatus($status))
            ->latest() // ORDER BY created_at DESC
            ->paginate(10); // LIMIT/OFFSET + отдельный COUNT(*); номер страницы берётся из ?page=

        return LoanApplicationResource::collection($applications);
    }

    /**
     * GET /api/loan-applications/stats — сколько заявок в каждом статусе. С кешем.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Cache::remember: есть в кеше — вернуть; нет — выполнить функцию, положить на 10 минут и вернуть.
        // Аналог: $cache->initCache() / startDataCache() / endDataCache() в Битриксе, но в одну строку.
        //
        // Cache — это ФАСАД. Выглядит как статический вызов, но на деле Laravel достаёт из контейнера
        // объект кеша и вызывает метод у него. Поэтому в тестах фасады легко подменять:
        // Queue::fake(), Cache::shouldReceive(...) — см. tests/Feature/SubmitLoanApplicationTest.php.
        //
        // Сброс кеша — в LoanApplication::booted(): при любом сохранении или удалении заявки.
        $stats = Cache::remember(
            LoanApplication::statsCacheKey($user->id),
            now()->addMinutes(10),
            function () use ($user) {
                // SELECT status, COUNT(*) AS total FROM loan_applications
                // WHERE user_id = ? AND deleted_at IS NULL GROUP BY status
                $counts = $user->loanApplications()
                    ->toBase() // спускаемся с Eloquent на обычный Query Builder: нам нужны числа, а не модели
                    ->selectRaw('status, COUNT(*) AS total')
                    ->groupBy('status')
                    ->pluck('total', 'status'); // Collection вида ['draft' => 2, 'approved' => 1]

                // Статусы, по которым заявок нет, в выборку не попали — добиваем нулями.
                return collect(ApplicationStatus::cases())
                    ->mapWithKeys(fn (ApplicationStatus $status) => [
                        $status->value => (int) ($counts[$status->value] ?? 0),
                    ])
                    ->all();
            },
        );

        return response()->json(['data' => $stats]);
    }

    /**
     * POST /api/loan-applications — создать черновик.
     */
    public function store(SaveDraftRequest $request): JsonResponse
    {
        // validated() — только те поля, что описаны в rules(). Лишнее из запроса сюда не попадёт.
        // create() через связь сам проставит user_id текущего пользователя.
        $application = $request->user()
            ->loanApplications()
            ->create($request->validated());

        return (new LoanApplicationResource($application))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED); // 201
    }

    /**
     * GET /api/loan-applications/{id}
     */
    public function show(LoanApplication $loanApplication): LoanApplicationResource
    {
        // Спросит LoanApplicationPolicy::view(). Нельзя — бросит исключение, клиент получит 403.
        Gate::authorize('view', $loanApplication);

        // load() — догрузить связь для уже полученной модели (with() — то же самое, но на этапе запроса).
        return new LoanApplicationResource($loanApplication->load('stopFactorResults'));
    }

    /**
     * PUT/PATCH /api/loan-applications/{id} — сохранить черновик.
     */
    public function update(SaveDraftRequest $request, LoanApplication $loanApplication): LoanApplicationResource
    {
        Gate::authorize('update', $loanApplication);
        $this->ensureDraft($loanApplication);

        $loanApplication->update($request->validated());

        return new LoanApplicationResource($loanApplication);
    }

    /**
     * DELETE /api/loan-applications/{id} — удалить черновик (мягко, см. SoftDeletes в модели).
     */
    public function destroy(LoanApplication $loanApplication): Response
    {
        Gate::authorize('delete', $loanApplication);
        $this->ensureDraft($loanApplication);

        $loanApplication->delete();

        return response()->noContent(); // 204
    }

    /**
     * POST /api/loan-applications/{id}/submit — отправить черновик на проверку.
     *
     * К моменту входа в метод SubmitLoanApplicationRequest уже проверил,
     * что заявка своя и что все поля заполнены.
     */
    public function submit(SubmitLoanApplicationRequest $request, LoanApplication $loanApplication): JsonResponse
    {
        $this->ensureDraft($loanApplication);

        // status нет в #[Fillable] — через update([...]) его не поменять, только явным присваиванием.
        $loanApplication->status = ApplicationStatus::Submitted;
        $loanApplication->submitted_at = now();
        $loanApplication->save();

        // Кладём задачу в очередь и сразу отвечаем. Проверки выполнит воркер (php artisan queue:work).
        RunStopFactorChecks::dispatch($loanApplication);

        return (new LoanApplicationResource($loanApplication))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED); // 202: «принято, результат будет позже»
    }

    /**
     * Менять, удалять и отправлять можно только черновик.
     * abort_unless бросает HTTP-исключение, которое Laravel превратит в JSON-ответ с этим кодом.
     */
    private function ensureDraft(LoanApplication $application): void
    {
        abort_unless(
            $application->status->isEditable(),
            Response::HTTP_CONFLICT, // 409
            'Заявка уже отправлена, изменить её нельзя.',
        );
    }
}
