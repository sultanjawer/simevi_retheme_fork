<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use LaravelDaily\LaravelCharts\Classes\LaravelChart;
use App\Models\Belanja;
use App\Models\DataRenja;
use App\Models\DataPagu;
use App\Models\DataRealisasi;
use App\Models\BackdateBanpem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    
    public function index(Request $request)
    {   
        if (Gate::allows('dashboard_access')){
            $breadcrumb = trans('cruds.dashboard.title_singular');
            return view('landing', compact('breadcrumb'));
        } else {
            if (Gate::allows('dashboardvip_access')){
                return $this->vip($request);
            }
        }
    
    }

    /**
     * Set locale.
     *
     * void
     */
    public function adminSetLocale(Request $request)
    {
       
        session()->put('language', request('lang'));
        
        //return redirect(url());
        return response(app()->getLocale());
    }
    /**
     * Show vip summary.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function vip(Request $request)
    {
        
        $agent = new Agent();

        //chart Renja
        //$sumyears = DataRenja::select(DB::raw('thang, SUM(jumlah) as totaljum'))->groupBy('thang')->offset(0)->limit(4)->orderBy('thang', 'DESC')->get();
        //end chart renja

        //chart (kinerja anggaran)
        $str = 'SELECT tabdata.tahun, GROUP_CONCAT(tabdata.totalpagu) as pagu, GROUP_CONCAT(tabdata.totalrealisasi) as realisasi FROM
                (
                select p.tahun, sum(p.amount) as totalpagu, null as totalrealisasi
                FROM data_pagus p 
                GROUP BY p.tahun
                union all
                select r.tahun, null as totalpagu, sum(r.amount) as totalrealisasi
                from data_realisasis r
                GROUP BY r.tahun
                ) as tabdata 
                GROUP BY tabdata.tahun';
        $prData = DB::select(DB::raw($str));
        //end chart (kinerja anggaran)
        
        //chart belanja 526
        $banpemyear = BackdateBanpem::select(DB::raw('year, SUM(nominal) as total'))->groupBy('year')->offset(0)->limit(4)->orderBy('year', 'DESC')->get();
        $databanpem = BackdateBanpem::select(DB::raw('year, kwn, format(SUM(nominal)/1000000000,2) as total'))->groupBy(['year', 'kwn'])->orderBy('kwn', 'asc')->get();
        
        $str = 'SELECT tabdata.tahun, FORMAT(GROUP_CONCAT(tabdata.totalpagu),0) as pagu, FORMAT(GROUP_CONCAT(tabdata.totalrealisasi),0) as realisasi ,
        FORMAT((GROUP_CONCAT(tabdata.totalrealisasi)/GROUP_CONCAT(tabdata.totalpagu))*100,2) as nilai
        FROM
        (
        select p.tahun,  sum(p.amount) as totalpagu, null as totalrealisasi
        FROM data_pagus p 
        where SUBSTR(p.akun, 1, 3) = "526"
        GROUP BY p.tahun
        
        union ALL
        
        select b.`year`, null as totalpagu,  sum(b.nominal) as totalrealisasi
        FROM backdate_banpems b
        GROUP BY b.year
        
        ) as tabdata 
        
        GROUP BY tabdata.tahun';
        $pbData = DB::select(DB::raw($str));
        
        $breadcrumb = trans('cruds.dashboardvip.title_singular');
        return view('admin.dashboard.vip', compact('banpemyear', 'databanpem', 'pbData', 'prData',  'agent', 'breadcrumb'));
    }

    /**
     * Show executive summary.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function executive()
    {
        $breadcrumb = trans('cruds.executive.title_singular');
        return view('admin.dashboard.executive', compact('breadcrumb'));
    }

    /**
     * Show pagu dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function pagu(Request $request)
    {
        $dtYear1 = ($request->dtYear1 ?? '');
        $dtYear2 = ($request->dtYear2 ?? '');

        if ($dtYear1 == ''){
            $qryYear1 = '';
        } else {
            if ($dtYear1 == ''){
                $qryYear1 = ' = "' . $dtYear1 . '"';
            } else {
                $qryYear1 = ' between "' . $dtYear1 . '"';
            }
        }

        if ($dtYear2 == ''){
            $qryYear2 = '';
        } else {
            $qryYear2 = ' and "' . $dtYear2 . '"';   
        }

        //Log::info([$qryYear1, $qryYear2]);

        $breadcrumb = trans('cruds.pagu.title_singular');
        $years =  DataPagu::distinct()->orderBy('tahun', 'ASC')->get(['tahun']);

        $st = 'select 
        format(GROUP_CONCAT(dbdata.pagus),0, "id_ID") pagus , format(GROUP_CONCAT(dbdata.reals),0, "id_ID") reals, 
        Format(COALESCE(GROUP_CONCAT(dbdata.reals),0)/COALESCE(GROUP_CONCAT(dbdata.pagus),0) * 100, 2) as persen,
        format(GROUP_CONCAT(dbdata.KP_PAGU),0, "id_ID") KP_PAGU , format(GROUP_CONCAT(dbdata.KP_REAL),0, "id_ID") KP_REAL, 
        Format(COALESCE(GROUP_CONCAT(dbdata.KP_REAL),0)/COALESCE(GROUP_CONCAT(dbdata.pagus),0) * 100, 0) as persenkp,
        format(GROUP_CONCAT(dbdata.DK_PAGU),0, "id_ID") DK_PAGU , format(GROUP_CONCAT(dbdata.DK_REAL),0, "id_ID") DK_REAL, 
        Format(COALESCE(GROUP_CONCAT(dbdata.DK_REAL),0)/COALESCE(GROUP_CONCAT(dbdata.pagus),0) * 100, 0) as persendk,
        format(GROUP_CONCAT(dbdata.TP_PAGU),0, "id_ID") TP_PAGU , format(GROUP_CONCAT(dbdata.TP_REAL),0, "id_ID") TP_REAL ,
        Format(COALESCE(GROUP_CONCAT(dbdata.TP_REAL),0)/COALESCE(GROUP_CONCAT(dbdata.pagus),0) * 100, 0) as persentp
        
        FROM
        (
        select sum(data_pagus.amount) pagus, null reals , null as KP_PAGU, null as KP_REAL, null as DK_PAGU, null as DK_REAL, null as TP_PAGU, null as TP_REAL FROM
        data_pagus where data_pagus.tahun '.$qryYear1.$qryYear2.'
        union ALL
        select null pagus, sum(data_realisasis.amount) reals , null as KP_PAGU, null as KP_REAL, null as DK_PAGU, null as DK_REAL, null as TP_PAGU, null as TP_REAL  FROM
        data_realisasis where data_realisasis.tahun '.$qryYear1.$qryYear2.'
        union all
        select  null pagus, null reals , sum(data_pagus.amount) KP_PAGU, null as KP_REAL, null as DK_PAGU, null as DK_REAL, null as TP_PAGU, null as TP_REAL FROM
        data_pagus where data_pagus.tahun '.$qryYear1.$qryYear2.' and data_pagus.kewenangan = 1
        UNION ALL
        select  null pagus, null reals , null KP_PAGU,sum(data_realisasis.amount) KP_REAL, null as DK_PAGU, null as DK_REAL, null as TP_PAGU, null as TP_REAL FROM
        data_realisasis where data_realisasis.tahun '.$qryYear1.$qryYear2.' and data_realisasis.kewenangan = 1
        UNION ALL
        select  null pagus, null reals , null as KP_PAGU, null as KP_REAL, sum(data_pagus.amount) DK_PAGU, null as DK_REAL, null as TP_PAGU, null as TP_REAL FROM
        data_pagus where data_pagus.tahun '.$qryYear1.$qryYear2.' and data_pagus.kewenangan = 3
        UNION ALL
        select  null pagus, null reals , null KP_PAGU,null as KP_REAL, null as DK_PAGU, sum(data_realisasis.amount) DK_REAL, null as TP_PAGU, null as TP_REAL FROM
        data_realisasis where data_realisasis.tahun '.$qryYear1.$qryYear2.' and data_realisasis.kewenangan = 3
        UNION ALL
        select  null pagus, null reals , null as KP_PAGU, null as KP_REAL, null as DK_PAGU, null as DK_REAL, sum(data_pagus.amount) TP_PAGU, null as TP_REAL FROM
        data_pagus where data_pagus.tahun '.$qryYear1.$qryYear2.' and data_pagus.kewenangan = 4
        UNION ALL
        select  null pagus, null reals , null KP_PAGU,null as KP_REAL, null as DK_PAGU, null as DK_REAL, null as TP_PAGU, sum(data_realisasis.amount) TP_REAL FROM
        data_realisasis where data_realisasis.tahun '.$qryYear1.$qryYear2.' and data_realisasis.kewenangan = 4
        ) as dbdata';
        
        $prData = DB::select(DB::raw($st));
    

        
        //Log::info($prData);

        return view('admin.dashboard.pagu', compact('breadcrumb', 'years', 'dtYear1', 'dtYear2', 'prData'));
    }

    /**
     * Show banpem dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function banpem()
    {
        $breadcrumb = trans('cruds.banpem.title_singular');
        return view('admin.dashboard.banpem', compact('breadcrumb'));
    }
    
}

