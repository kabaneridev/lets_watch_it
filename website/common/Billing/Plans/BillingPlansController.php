<?php namespace Common\Billing\Plans;

use Common\Billing\BillingPlan;
use Common\Billing\Gateways\Contracts\GatewayInterface;
use Common\Billing\Gateways\GatewayFactory;
use Common\Billing\Plans\Actions\CrupdateBillingPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Common\Core\BaseController;
use Common\Database\Paginator;
use Illuminate\Http\Response;

class BillingPlansController extends BaseController
{
    /**
     * @var BillingPlan
     */
    private $plan;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var GatewayFactory
     */
    private $factory;

    /**
     * @param BillingPlan $plan
     * @param Request $request
     * @param GatewayFactory $factory
     */
    public function __construct(BillingPlan $plan, Request $request, GatewayFactory $factory)
    {
        $this->plan = $plan;
        $this->request = $request;
        $this->factory = $factory;
    }

    /**
     * @return JsonResponse
     */
    public function index()
    {
        $this->authorize('index', BillingPlan::class);

        $paginator = (new Paginator($this->plan, $this->request->all()))
            ->with(['parent', 'permissions']);
        $paginator->filterColumns = ['currency', 'interval', 'parent_id', 'recommended'];
        $pagination = $paginator->paginate();

        return $this->success(['pagination' => $pagination]);
    }

    /**
     * @return BillingPlan|JsonResponse
     */
    public function store()
    {
        $this->authorize('store', BillingPlan::class);

        $this->validate($this->request, [
            'name' => 'required|string|max:250',
            'currency' => 'required_unless:free,1|string|max:255',
            'interval' => 'required_unless:free,1|string|max:255',
            'amount' => 'required_unless:free,1|min:0',
            'permissions' => 'array',
            'show_permissions' => 'required|boolean',
            'recommended' => 'required|boolean',
            'position' => 'required|integer',
            'available_space' => 'nullable|integer|min:1'
        ]);

        $plan = app(CrupdateBillingPlan::class)->execute($this->request->all());

        return $this->success(['plan' => $plan]);
    }

    /**
     * @param BillingPlan $billingPlan
     * @return Response
     */
    public function update(BillingPlan $billingPlan)
    {
        $this->authorize('update', $billingPlan);

        $this->validate($this->request, [
            'name' => 'required|string|max:250',
            'currency' => 'string|max:255',
            'interval' => 'string|max:255',
            'amount' => 'integer|min:0',
            'permissions' => 'array',
            'show_permissions' => 'boolean',
            'recommended' => 'boolean',
        ]);

        $plan = app(CrupdateBillingPlan::class)->execute(
            $this->request->except('parent'), $billingPlan
        );

        return $this->success(['plan' => $plan]);
    }

    /**
     * @param string $ids
     * @return Response
     */
    public function destroy($ids)
    {
        $planIds = explode(',', $ids);
        $this->authorize('destroy', [BillingPlan::class, $planIds]);

        foreach ($planIds as $id) {
            $plan = $this->plan->find($id);
            if (is_null($plan)) continue;

            $plan->delete();

            $this->factory->getEnabledGateways()->each(function(GatewayInterface $gateway) use($plan) {
                $gateway->plans()->delete($plan);
            });
        }

        return $this->success();
    }

    /**
     * Sync billing plans across all enabled payment gateways.
     */
    public function sync()
    {
        ini_set('max_execution_time', 300);

        $plans = $this->plan->where('free', false)->orderBy('parent_id', 'asc')->get();

        $this->factory->getEnabledGateways()->each(function(GatewayInterface $gateway) use($plans) {
            $plans->each(function(BillingPlan $plan) use($gateway) {
                if ($gateway->plans()->find($plan)) return;
                $gateway->plans()->create($plan);
            });
        });

        return $this->success();
    }
}
