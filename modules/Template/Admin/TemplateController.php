<?php
namespace Modules\Template\Admin;

use Illuminate\Http\Request;
use Modules\AdminController;
use Modules\Template\Models\Template;
use Modules\Template\Models\TemplateTranslation;

class TemplateController extends AdminController
{
    protected $templateClass;
    protected $templateTranslationClass;
    public function __construct()
    {
        parent::__construct();
        $this->templateClass = Template::class;
        $this->templateTranslationClass = TemplateTranslation::class;
    }

    public function index(Request $request)
    {
        $this->checkPermission('template_view');
        $this->setActiveMenu('admin/module/template');
        $query = $this->templateClass::query() ;
        $query->orderBy('id', 'desc');
        if (!empty($tour_name = $request->input('s'))) {
            $query->where('title', 'LIKE', '%' . $tour_name . '%');
            $query->orderBy('title', 'asc');
        }
        $data = [
            'rows'       => $query->paginate(20),
            'page_title' => __('Template Management')
        ];
        return view('Template::admin.index', $data);
    }

    public function create(Request $request)
    {
        $this->setActiveMenu('admin/module/template');
        $this->checkPermission('template_create');
        $row = new $this->templateClass();
        $data = [
            'row'         => $row,
            'breadcrumbs' => [
                [
                    'name' => __('Templates'),
                    'url'  => 'admin/module/template'
                ],
                [
                    'name'  => __('Create new template'),
                    'class' => 'active'
                ],
            ],
            'page_title'  => __('Create new Template'),
            'translation'=>new $this->templateTranslationClass()
        ];
        return view('Template::admin.detail', $data);
    }

    public function edit(Request $request, $id)
    {
        $this->checkPermission('template_update');
        $this->setActiveMenu('admin/module/template');
        $row = $this->templateClass::find($id);
        if (empty($row)) {
            return redirect('admin/module/template');
        }
        $translation = $row->translateOrOrigin($request->query('lang'));

        $data = [
            'row'         => $row,
            'breadcrumbs' => [
                [
                    'name' => __('Templates'),
                    'url'  => 'admin/module/template'
                ],
                [
                    'name'  => __('Edit Template: :title', ['title' => $row->title]),
                    'class' => 'active'
                ],
            ],
            'page_title'  => __('Edit Template: :title', ['title' => $row->title]),
            'translation'=>$translation,
            'enable_multi_lang'=>true
        ];
        return view('Template::admin.detail', $data);
    }

    public function getBlocks()
    {
        $template = new $this->templateClass();
        $this->sendSuccess(['data' => $template->getBlocks()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required',
            'title'   => 'required|max:255'
        ]);
        if ($request->input('id')) {
            $this->checkPermission('template_update');
            $template = $this->templateClass::find($request->input('id'));
        } else {
            $this->checkPermission('template_create');
            $template = new $this->templateClass();
        }
        if (empty($template))
            $this->sendError('Template not found');
        $template->content = $request->input('content');
        $template->title = $request->input('title');

        $template->saveOriginOrTranslation($request->input('lang'));

        $this->sendSuccess([
            'url' => $request->input('id') ? '' : url('admin/module/template/edit/' . $template->id)
        ], __('Your template has been saved'));
    }

    public function bulkEdit(Request $request)
    {
        $ids = $request->input('ids');
        $action = $request->input('action');
        if (empty($ids) or !is_array($ids)) {
            return redirect()->back()->with('error', __('No items selected!'));
        }
        if (empty($action)) {
            return redirect()->back()->with('error', __('Please select an action!'));
        }
        switch ($action){
            case "delete":
                foreach ($ids as $id) {
                    $query = $this->templateClass::where("id", $id);
                    $this->checkPermission('template_delete');
                    $query->first();
                    if(!empty($query)){
                        $query->delete();
                    }
                }
                return redirect()->back()->with('success', __('Deleted success!'));
                break;
            default:
                // Change status
                foreach ($ids as $id) {
                    $query = $this->templateClass::where("id", $id);
                    $this->checkPermission('template_update');
                    $query->update(['status' => $action]);
                }
                return redirect()->back()->with('success', __('Update success!'));
                break;
        }
    }
}