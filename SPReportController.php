<?php


namespace App\Controller;

use App\Entity\SPTag;
use App\Entity\User;
use App\Entity\SPPlacement;
use App\Entity\SPSchool;
use App\Entity\SPStudentAssignment;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;

use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SPReportController extends AbstractController
{
    /**
     * @Route("sp_footer/set_the_footer", name="sp_footer")
     */
    public function set_the_footer()
    {
        return $this->render('sp/reports/sp_footer.html.twig');
    }

    /**
     * @Route("sp/tutors/{tutor_id}/reports/{tag_id}")
     */
    public function generate_sp_tutor_report($tutor_id, $tag_id, EntityManagerInterface $em)
    {
        $session = $this->get('session');

        $ay = $session->get('academic_year');

        $core = new CoreObjects($em);

        $repository=$em->getRepository(User::class);
        if ($tutor_id>0) $tutor_list=$repository->findby(['id'=>$tutor_id]);
        if ($tutor_id==0) $tutor_list=$repository->findby(['user_is_sp_tutor'=>1]);

        $repository=$em->getRepository(SPPlacement::class);
        //$placement=$repository->findOneBy(['placement_academic_year'=>$session->get('academic_year'),'placement_tag'=>$tag_id]);

        if ($tag_id>0) $placement=$repository->findOneBy(['placement_academic_year'=>$ay,'placement_tag'=>$tag_id]);

        if ($tag_id<0)
        {
            $placement = new SPPlacement();
            if ($tag_id==-1) $placement->setPlacementName("Autumn Placements");
            if ($tag_id==-2) $placement->setPlacementName("Spring Placements");
            if ($tag_id==-3) $placement->setPlacementName("Repeat/Deferred Placements");
            $placement->tag_id=$tag_id;
        }


        $alloc_repository=$em->getRepository(SPStudentAssignment::class);
        //$allocations = $repository->findBy(['sp_assignment_academic_year'=>$session->get('academic_year'), 'sp_assignment_tag'=>$tag_id],['sp_assignment_school'=>'ASC']);



        $t_alloc = array();

        $html="";

        foreach ($tutor_list as $tutor)
        {

            if ($tag_id>0) $allocations = $alloc_repository->findBy(['sp_assignment_academic_year'=>$ay, 'sp_assignment_tag'=>$tag_id],['sp_assignment_school'=>'ASC']);
            if ($tag_id<0) $allocations = $alloc_repository->findBy(['sp_assignment_academic_year'=>$ay, 'sp_assignment_period'=>abs($tag_id)],['sp_assignment_school'=>'ASC']);

            $t_alloc = array();

            foreach ($allocations as $k=>$a)
            {
                $tutors = $a->getSpAssignmentTutors();

                $rm=true;
                foreach ($tutors as $l=>$t)
                {
                    if ($t==$tutor->getId()) $rm=false;
                }

                if ($rm) unset($allocations[$k]);
                else
                {
                    $repository=$em->getRepository(User::class);
                    //$tutor=$repository->find($tutor_id);
                    foreach ($tutors as $l=>$t)
                    {
                        $tutors[$l]=$repository->find($t);
                    }
                    //$allocations[$k]->setSpAssignmentTutors($tutors);
                    $t_alloc[$a->getId()]=$tutors;
                }

                $translated_classes = array();
                foreach ($a->getSpAssignmentClass() as $c)
                {
                    $translated_classes[]=$core->translate_internal_value("sp_class",$c);
                }
                $a->translated_classes=$translated_classes;

            }




            $html.=$this->renderView('sp/reports/sp_tutor_report.html.twig',['allocations'=>$allocations,'t_alloc'=>$t_alloc,'tutor'=>$tutor,'placement'=>$placement]);

        }

        //dd($allocations);

       // return $this->render('sp/reports/sp_tutor_report.html.twig',['allocations'=>$allocations,'t_alloc'=>$t_alloc,'tutor'=>$tutor,'placement'=>$placement]);
        return new Response($html);
    }

    /**
     * @Route("sp/tutors/{tutor_id}/reports/{tag_id}/download")
     */

    public function download_sp_tutor_report($tutor_id, $tag_id, EntityManagerInterface $em,\Knp\Snappy\Pdf $snappy)
    {
        $session = $this->get('session');

        $ay = $session->get('academic_year');

        $core = new CoreObjects($em);

        $repository=$em->getRepository(User::class);
        if ($tutor_id>0) $tutor_list=$repository->findby(['id'=>$tutor_id]);
        if ($tutor_id==0) $tutor_list=$repository->findby(['user_is_sp_tutor'=>1],['user_surname'=>'ASC', 'user_firstname'=>'ASC']);

        $repository=$em->getRepository(SPPlacement::class);
        //$placement=$repository->findOneBy(['placement_academic_year'=>$session->get('academic_year'),'placement_tag'=>$tag_id]);

        if ($tag_id>0) $placement=$repository->findOneBy(['placement_academic_year'=>$ay,'placement_tag'=>$tag_id]);

        if ($tag_id<0)
        {
            $placement = new SPPlacement();
            if ($tag_id==-1) $placement->setPlacementName("Autumn Placements");
            if ($tag_id==-2) $placement->setPlacementName("Spring Placements");
            if ($tag_id==-3) $placement->setPlacementName("Repeat/Deferred Placements");
            $placement->tag_id=$tag_id;
        }

        $sp_alloc_repository=$em->getRepository(SPStudentAssignment::class);
        //$allocations = $repository->findBy(['sp_assignment_academic_year'=>$session->get('academic_year'), 'sp_assignment_tag'=>$tag_id],['sp_assignment_school'=>'ASC']);



        $html="";

        //dd($tutor_list);

        foreach ($tutor_list as $tutor)
        {

            if ($tag_id>0) $allocations = $sp_alloc_repository->findBy(['sp_assignment_academic_year'=>$ay, 'sp_assignment_tag'=>$tag_id],['sp_assignment_school'=>'ASC']);
            if ($tag_id<0) $allocations = $sp_alloc_repository->findBy(['sp_assignment_academic_year'=>$ay, 'sp_assignment_period'=>abs($tag_id)],['sp_assignment_school'=>'ASC']);

            $t_alloc = array();

            foreach ($allocations as $k=>$a)
            {
                if ($a->getSpAssignmentPlacement()->getPlacementPaired() && (strlen($a->getSpAssignmentTeacher())==0 || $a->getSpAssignmentTeacher()==null)) unset($allocations[$k]);
            }

            foreach ($allocations as $k=>$a)
            {
                if ($a->getSpAssignmentPlacement()->getPlacementPaired() && strlen($a->getSpAssignmentTeacher())>0)
                {
                    $pairing = array();
                    foreach ($allocations as $a_id=>$a_data)
                    {

                        if ($a_data->getSpAssignmentTeacher()==$a->getSpAssignmentTeacher() && $a->getSpAssignmentSchool()==$a_data->getSpAssignmentSchool() && $a_data->getSpAssignmentPeriod()==$a->getSpAssignmentPeriod() && $a->getSpAssignmentAcademicYear()==$a_data->getSpAssignmentAcademicYear() && $a_data->getSpAssignmentTag()==$a->getSpAssignmentTag())
                        {
                            $pairing[]=$a_data->getSpAssignmentStudent();
                            if ($a_data->getSpAssignmentStudent()!=$a->getSpAssignmentStudent())
                            {
                                $allocations[$a_id]->pairing=$pairing;
                                unset($allocations[$k]);
                                break;
                            }

                        }

                    }

                }

            }

            foreach ($allocations as $k=>$a)
            {

                $tutors = $a->getSpAssignmentTutors();

                $rm=true;
                foreach ($tutors as $l=>$t)
                {
                    if ($t==$tutor->getId()) $rm=false;
                }

                if ($rm) unset($allocations[$k]);
                else
                {
                    $repository=$em->getRepository(User::class);
                    //$tutor=$repository->find($tutor_id);
                    foreach ($tutors as $l=>$t)
                    {
                        $tutors[$l]=$repository->find($t);
                    }
                    //$allocations[$k]->setSpAssignmentTutors($tutors);
                    $t_alloc[$a->getId()]=$tutors;
                }

                $translated_classes = array();
                foreach ($a->getSpAssignmentClass() as $c)
                {
                    $translated_classes[]=$core->translate_internal_value("sp_class",$c);
                }
                $a->translated_classes=$translated_classes;

            }


//            return $this->render('sp/reports/sp_tutor_report_export.html.twig',['allocations'=>$allocations,'t_alloc'=>$t_alloc,'tutor'=>$tutor,'placement'=>$placement]);

            if (count($allocations)>0) $html.=$this->renderView('sp/reports/sp_tutor_report_export.html.twig',['allocations'=>$allocations,'t_alloc'=>$t_alloc,'tutor'=>$tutor,'placement'=>$placement,'tutor_id'=>$tutor_id]);



        }

        $html=$this->renderView('sp/reports/sp_tutor_report_export_frame.html.twig',['content'=>$html]);
        //file_put_contents('/home/user/M5/files_tmp/debug_sp.html',$html);
        //dd("RDY");

        if ($tutor_id>0)
        {
            $tname = $tutor->getUserSurname()."_".$tutor->getUserFirstname();
            $tname = str_replace("'","",$tname);
            $tname = str_replace(" ","_",$tname);
            $tname = str_replace("í","i",$tname);
            $tname = str_replace("Í","I",$tname);
            $tname = str_replace("é","e",$tname);
            $tname = str_replace("É","E",$tname);
            $tname = str_replace("á","a",$tname);
            $tname = str_replace("Á","A",$tname);
            $tname = str_replace("ó","o",$tname);
            $tname = str_replace("Ó","O",$tname);
            $tname = str_replace("ú","u",$tname);
            $tname = str_replace("Ú","U",$tname);
        }
        else
        {
            $tname = "All_Tutors";
        }


        $pname = $placement->getPlacementName();
        $pname = str_replace("'","",$pname);
        $pname = str_replace(" ","_",$pname);
        $pname = str_replace("í","i",$pname);
        $pname = str_replace("Í","I",$pname);
        $pname = str_replace("é","e",$pname);
        $pname = str_replace("É","E",$pname);
        $pname = str_replace("á","a",$pname);
        $pname = str_replace("Á","A",$pname);
        $pname = str_replace("ó","o",$pname);
        $pname = str_replace("Ó","O",$pname);
        $pname = str_replace("ú","u",$pname);
        $pname = str_replace("Ú","U",$pname);

        $snappy->setOption('orientation', 'portrait');
        $snappy->setOption('enable-local-file-access',true);
        $snappy->setOption('viewport-size', '1280x1024');
        $snappy->setTimeout(300);
//        $snappy->setOption('javascript-delay', '1000');
//        $snappy->setOption('no-stop-slow-scripts', true);
//        $snappy->setOption('enable-local-file-access',true);
        $snappy->setOption('margin-top', '10mm');
        $snappy->setOption('margin-bottom', '25mm');
        $snappy->setOption('footer-font-size', 8);
        $snappy->setOption('encoding', 'UTF-8');

        $url = $this->generateUrl('sp_footer', array(''), UrlGeneratorInterface::ABSOLUTE_URL);
//        dd($url);
        $snappy->setOption('footer-html', $url);
//        $snappy->setOption('footer-spacing', '10');


        //header('Content-Type: application/pdf');
        //echo $snappy->getOutputFromHtml($html);

        return new PdfResponse(
            $snappy->getOutputFromHtml($html),
            'SP_'. $tname .'_'.str_replace("/","",$pname).'.pdf'
        );
    }

    public function server_sp_tutor_report($tutor_id, $tag_id, EntityManagerInterface $em,\Knp\Snappy\Pdf $snappy)
    {
        $session = $this->get('session');

        $ay = $session->get('academic_year');

        $core = new CoreObjects($em);

        $repository=$em->getRepository(User::class);
        if ($tutor_id>0) $tutor_list=$repository->findby(['id'=>$tutor_id]);
        if ($tutor_id==0) $tutor_list=$repository->findby(['user_is_sp_tutor'=>1],['user_surname'=>'ASC', 'user_firstname'=>'ASC']);

        $repository=$em->getRepository(SPPlacement::class);
        //$placement=$repository->findOneBy(['placement_academic_year'=>$session->get('academic_year'),'placement_tag'=>$tag_id]);

        if ($tag_id>0) $placement=$repository->findOneBy(['placement_academic_year'=>$ay,'placement_tag'=>$tag_id]);

        if ($tag_id<0)
        {
            $placement = new SPPlacement();
            if ($tag_id==-1) $placement->setPlacementName("Autumn Placements");
            if ($tag_id==-2) $placement->setPlacementName("Spring Placements");
            if ($tag_id==-3) $placement->setPlacementName("Repeat/Deferred Placements");
            $placement->tag_id=$tag_id;
        }

        $sp_alloc_repository=$em->getRepository(SPStudentAssignment::class);
        //$allocations = $repository->findBy(['sp_assignment_academic_year'=>$session->get('academic_year'), 'sp_assignment_tag'=>$tag_id],['sp_assignment_school'=>'ASC']);



        $html="";

        //dd($tutor_list);

        foreach ($tutor_list as $tutor)
        {

            if ($tag_id>0) $allocations = $sp_alloc_repository->findBy(['sp_assignment_academic_year'=>$ay, 'sp_assignment_tag'=>$tag_id],['sp_assignment_school'=>'ASC']);
            if ($tag_id<0) $allocations = $sp_alloc_repository->findBy(['sp_assignment_academic_year'=>$ay, 'sp_assignment_period'=>abs($tag_id)],['sp_assignment_school'=>'ASC']);

            $t_alloc = array();

            foreach ($allocations as $k=>$a)
            {
                if ($a->getSpAssignmentPlacement()->getPlacementPaired() && (strlen($a->getSpAssignmentTeacher())==0 || $a->getSpAssignmentTeacher()==null)) unset($allocations[$k]);
            }

            foreach ($allocations as $k=>$a)
            {
                if ($a->getSpAssignmentPlacement()->getPlacementPaired() && strlen($a->getSpAssignmentTeacher())>0)
                {
                    $pairing = array();
                    foreach ($allocations as $a_id=>$a_data)
                    {

                        if ($a_data->getSpAssignmentTeacher()==$a->getSpAssignmentTeacher() && $a->getSpAssignmentSchool()==$a_data->getSpAssignmentSchool() && $a_data->getSpAssignmentPeriod()==$a->getSpAssignmentPeriod() && $a->getSpAssignmentAcademicYear()==$a_data->getSpAssignmentAcademicYear() && $a_data->getSpAssignmentTag()==$a->getSpAssignmentTag())
                        {
                            $pairing[]=$a_data->getSpAssignmentStudent();
                            if ($a_data->getSpAssignmentStudent()!=$a->getSpAssignmentStudent())
                            {
                                $allocations[$a_id]->pairing=$pairing;
                                unset($allocations[$k]);
                                break;
                            }

                        }

                    }

                }

            }

            foreach ($allocations as $k=>$a)
            {

                $tutors = $a->getSpAssignmentTutors();

                $rm=true;
                foreach ($tutors as $l=>$t)
                {
                    if ($t==$tutor->getId()) $rm=false;
                }

                if ($rm) unset($allocations[$k]);
                else
                {
                    $repository=$em->getRepository(User::class);
                    //$tutor=$repository->find($tutor_id);
                    foreach ($tutors as $l=>$t)
                    {
                        $tutors[$l]=$repository->find($t);
                    }
                    //$allocations[$k]->setSpAssignmentTutors($tutors);
                    $t_alloc[$a->getId()]=$tutors;
                }

                $translated_classes = array();
                foreach ($a->getSpAssignmentClass() as $c)
                {
                    $translated_classes[]=$core->translate_internal_value("sp_class",$c);
                }
                $a->translated_classes=$translated_classes;

            }


//            return $this->render('sp/reports/sp_tutor_report_export.html.twig',['allocations'=>$allocations,'t_alloc'=>$t_alloc,'tutor'=>$tutor,'placement'=>$placement]);

            if (count($allocations)>0) $html.=$this->renderView('sp/reports/sp_tutor_report_export.html.twig',['allocations'=>$allocations,'t_alloc'=>$t_alloc,'tutor'=>$tutor,'placement'=>$placement,'tutor_id'=>$tutor_id]);



        }



        //dd($allocations);


        $tname = $tutor->getUserSurname()."_".$tutor->getUserFirstname();
        $tname = str_replace("'","",$tname);
        $tname = str_replace(" ","_",$tname);
        $tname = str_replace("í","i",$tname);
        $tname = str_replace("Í","I",$tname);
        $tname = str_replace("é","e",$tname);
        $tname = str_replace("É","E",$tname);
        $tname = str_replace("á","a",$tname);
        $tname = str_replace("Á","A",$tname);
        $tname = str_replace("ó","o",$tname);
        $tname = str_replace("Ó","O",$tname);
        $tname = str_replace("ú","u",$tname);
        $tname = str_replace("Ú","U",$tname);


        $pname = $placement->getPlacementName();
        $pname = str_replace("'","",$pname);
        $pname = str_replace(" ","_",$pname);
        $pname = str_replace("í","i",$pname);
        $pname = str_replace("Í","I",$pname);
        $pname = str_replace("é","e",$pname);
        $pname = str_replace("É","E",$pname);
        $pname = str_replace("á","a",$pname);
        $pname = str_replace("Á","A",$pname);
        $pname = str_replace("ó","o",$pname);
        $pname = str_replace("Ó","O",$pname);
        $pname = str_replace("ú","u",$pname);
        $pname = str_replace("Ú","U",$pname);



        //$html=$this->renderView('sp/reports/sp_tutor_report_export.html.twig',['allocations'=>$allocations,'t_alloc'=>$t_alloc,'tutor'=>$tutor,'placement'=>$placement,'tutor_id'=>$tutor_id]);
        $html=$this->renderView('sp/reports/sp_tutor_report_export_frame.html.twig',['content'=>$html]);


        $snappy->setOption('orientation', 'portrait');
        $snappy->setOption('enable-local-file-access',true);
        $snappy->setOption('viewport-size', '1280x1024');
        $snappy->setTimeout(300);
        $snappy->setOption('margin-top', '10mm');
        $snappy->setOption('margin-bottom', '25mm');

        //header('Content-Type: application/pdf');
        //echo $snappy->getOutputFromHtml($html);
        $tmpfolder = $this->getParameter('files_tmp');
        //dd($tmpfolder);

        if (file_exists($tmpfolder.'/SP_'. $tname .'_'.str_replace("/","",$pname).'.pdf'))
        {
            unlink($tmpfolder.'/SP_'. $tname .'_'.str_replace("/","",$pname).'.pdf');
        }

        $snappy -> generateFromHtml($html, $tmpfolder.'/SP_'. $tname .'_'.str_replace("/","",$pname).'.pdf');

        $fname=$tmpfolder.'/SP_'. $tname .'_'.str_replace("/","",$pname).'.pdf';



        return $fname;
    }

    public function server_sp_school_report($school_id, $period_id, EntityManagerInterface $em,\Knp\Snappy\Pdf $snappy)
    {
        $session = $this->get('session');

        $ay = $session->get('academic_year');

        $repository=$em->getRepository(SPSchool::class);
        $school=$repository->find($school_id);

        $repository=$em->getRepository(SPPlacement::class);
        //$placement=$repository->findOneBy(['placement_academic_year'=>$session->get('academic_year'),'placement_tag'=>$tag_id]);

        //$placement=$repository->findOneBy(['placement_academic_year'=>9,'placement_tag'=>$tag_id]);

        $repository=$em->getRepository(SPStudentAssignment::class);
        //$allocations = $repository->findBy(['sp_assignment_academic_year'=>$session->get('academic_year'), 'sp_assignment_tag'=>$tag_id],['sp_assignment_school'=>'ASC']);

        //$allocations = $repository->findBy(['sp_assignment_academic_year'=>$ay, 'sp_assignment_period'=>$period_id,'sp_assignment_school'=>$school_id],['sp_assignment_placement'=>'ASC']);
        $allocations = $repository->findSchoolAssignmentsByDate($ay,$period_id,$school_id);
        $empty_allocations = $repository->findUnallocatedSchoolAssignments($ay,$period_id,$school_id);

        $core = new CoreObjects($em);

        foreach ($allocations as $k=>$a)
        {
            if ($a->getSpAssignmentPlacement()->getPlacementPaired() && (strlen($a->getSpAssignmentTeacher())==0 || $a->getSpAssignmentTeacher()==null)) unset($allocations[$k]);
        }

        foreach ($allocations as $k=>$a)
        {
            if ($a->getSpAssignmentPlacement()->getPlacementPaired() && strlen($a->getSpAssignmentTeacher())>0)
            {
                $pairing = array();
                foreach ($allocations as $a_id=>$a_data)
                {

                    if ($a_data->getSpAssignmentTeacher()==$a->getSpAssignmentTeacher() && $a->getSpAssignmentSchool()==$a_data->getSpAssignmentSchool() && $a_data->getSpAssignmentPeriod()==$a->getSpAssignmentPeriod() && $a->getSpAssignmentAcademicYear()==$a_data->getSpAssignmentAcademicYear() && $a_data->getSpAssignmentTag()==$a->getSpAssignmentTag())
                    {
                        $pairing[]=$a_data->getSpAssignmentStudent();
                        if ($a_data->getSpAssignmentStudent()!=$a->getSpAssignmentStudent())
                        {
                            $allocations[$a_id]->pairing=$pairing;
                            unset($allocations[$k]);
                            break;
                        }

                    }

                }

            }

        }


        foreach ($allocations as $a)
        {



            $translated_classes = array();
            foreach ($a->getSpAssignmentClass() as $c)
            {
                $translated_classes[]=$core->translate_internal_value("sp_class",$c);
            }
            $a->translated_classes=$translated_classes;


        }

        foreach ($empty_allocations as $a)
        {
            $translated_classes = array();
            foreach ($a->getSpAssignmentClass() as $c)
            {
                $translated_classes[]=$core->translate_internal_value("sp_class",$c);
            }
            $a->translated_classes=$translated_classes;


        }

        $sname = $school->getSPSchoolName();
        $sname = str_replace("'","",$sname);
        $sname = str_replace(" ","_",$sname);
        $sname = str_replace("í","i",$sname);
        $sname = str_replace("Í","I",$sname);
        $sname = str_replace("é","e",$sname);
        $sname = str_replace("É","E",$sname);
        $sname = str_replace("á","a",$sname);
        $sname = str_replace("Á","A",$sname);
        $sname = str_replace("ó","o",$sname);
        $sname = str_replace("Ó","O",$sname);
        $sname = str_replace("ú","u",$sname);
        $sname = str_replace("Ú","U",$sname);

        $period_name="";

        if ($period_id==0) {$period_name="Unset"; $fperiod="Unset";}
        if ($period_id==1) {$period_name="Fómhair"; $fperiod="Fomhair";}
        if ($period_id==2) {$period_name="Earrach"; $fperiod="Earrach";}
        if ($period_id==3) {$period_name="Summer"; $fperiod="Summer";}
        //dd($allocations);

        $html=$this->renderView('sp/reports/sp_school_report.html.twig',['allocations'=>$allocations,'unallocated'=>$empty_allocations,'school'=>$school,'period'=>$period_id, 'period_name'=>$period_name, 'ay'=>$ay]);

        $snappy->setOption('orientation', 'portrait');
        $snappy->setOption('enable-local-file-access',true);

        $tmpfolder = $this->getParameter('files_tmp');
        //dd($tmpfolder);

        if (file_exists($tmpfolder.'/SP_'. $sname .'_'.$fperiod.'.pdf'))
        {
            unlink($tmpfolder.'/SP_'. $sname .'_'.$fperiod.'.pdf');
        }

        $snappy -> generateFromHtml($html, $tmpfolder.'/SP_'. $sname .'_'.$fperiod.'.pdf');

        $fname=$tmpfolder.'/SP_'. $sname .'_'.$fperiod.'.pdf';



        return $fname;
    }

    /**
     * @Route("sp/mailing/schools")
     */
    public function mail_schools(EntityManagerInterface $em,\Knp\Snappy\Pdf $snappy,MailerInterface $mailer)
    {
        $period = $_POST['period'];
        if (isset($_POST['tags'])) $tags = $_POST['tags']; else $tags=array();
        $target_school=$_POST['school'];


        $session = $this->get('session');

        $tags_repository=$em->getRepository(SPTag::class);
        $assg_repository=$em->getRepository(SPStudentAssignment::class);

        $school_tags=array();

        foreach ($tags as $t)
        {
            $tag = $tags_repository->find($t);
            $assg = $assg_repository->findBy(['sp_assignment_academic_year'=>$session->get('academic_year'), 'sp_assignment_tag'=>$tag]);

            foreach ($assg as $a)
            {
                $school = $a->getSpAssignmentSchool()->getId();
                if ($school==$target_school || $target_school==0)
                {
                    if (!isset($school_tags[$school]) || !in_array($t,$school_tags[$school])) {$school_tags[$school][]=$t;}
                }
            }
        }

        unset($school_tags[0]);

        $school_repository=$em->getRepository(SPSchool::class);

        foreach ($school_tags as $school_id => $mail_tags)
        {
            $school = $school_repository->find($school_id);

            $subj = $school->getSpSchoolEmail();

            $att=array();


                $filename=@$this->server_sp_school_report($school->getId(),$period, $em,$snappy);
                while(is_file($filename)==false)
                {
                    sleep(0.5);
                }
                $att[]=$filename;




            $comms = new CommsController();
            $comms->send_email(1,"deirdre.nimhurchu@mie.ie",$subj,"","",$att,$mailer,$em);
            //$comms->send_email(1,"piotr.korta@mie.ie",$subj,"","",$att,$mailer,$em);
        }



        return new Response("OK");
    }

    /**
     * @Route("sp/mailing/tutors")
     */
    public function mail_tutors(EntityManagerInterface $em,\Knp\Snappy\Pdf $snappy,MailerInterface $mailer)
    {

        $tags = $_POST['tags'];
        $target_tutor=$_POST['tutor'];


        $session = $this->get('session');

        $tags_repository=$em->getRepository(SPTag::class);
        $assg_repository=$em->getRepository(SPStudentAssignment::class);

        $user_tags=array();

        foreach ($tags as $t)
        {
            $tag = $tags_repository->find($t);
            if ($t>0) $assg = $assg_repository->findBy(['sp_assignment_academic_year'=>$session->get('academic_year'), 'sp_assignment_tag'=>$tag]);
            if ($t<0) $assg = $assg_repository->findBy(['sp_assignment_academic_year'=>$session->get('academic_year'), 'sp_assignment_period'=>abs($t)],['sp_assignment_school'=>'ASC']);


            foreach ($assg as $a)
            {
                $assg_tutors = $a->getSpAssignmentTutors();
                foreach ($assg_tutors as $tutor) if ($tutor==$target_tutor || $target_tutor==0)
                {
                    if (!isset($user_tags[$tutor]) || !in_array($t,$user_tags[$tutor])) {$user_tags[$tutor][]=$t;}
                }
            }
        }

        unset($user_tags[0]);

        $user_repository=$em->getRepository(User::class);

        foreach ($user_tags as $user_id => $mail_tags)
        {
            $user = $user_repository->find($user_id);

            if ($user->getUserEmail()) $subj = $user->getUserEmail(); else $subj = $user->getUserSurname()."".$user->getUserFirstName()." [EMAIL_UNKNOWN]";

            $att=array();


            foreach ($mail_tags as $t)
            {
                $filename=@$this->server_sp_tutor_report($user->getId(), $t, $em,$snappy);
                while(is_file($filename)==false)
                {
                    sleep(0.5);
                }
                $att[]=$filename;
            }


            $comms = new CommsController();
            $comms->send_email(1,"deirdre.nimhurchu@mie.ie",$subj,"","",$att,$mailer,$em);
            //$comms->send_email(1,"xinhao.chen@mie.ie",$subj,"","",$att,$mailer,$em);
            //$comms->send_email(1,"piotr.korta@mie.ie",$subj,"","",$att,$mailer,$em);
        }



        return new Response(count($user_tags));
    }


    /**
     * @Route("sp/ftest")
     */
    public function ftest(EntityManagerInterface $em,\Knp\Snappy\Pdf $snappy,MailerInterface $mailer)
    {
        $att=array();

        $att[]=$this->server_sp_tutor_report(501, 210, $em,$snappy);

        //dd($att);

        $comms = new CommsController();

        $comms->send_email(1,"piotr.korta@mie.ie","TEST SP","MSG CONTENT","",$att,$mailer,$em);

        return new Response("OK");
    }


    /**
     * @Route("sp/tutors/{tutor_id}/reports/{tag_id}/store")
     */
    function store_sp_tutor_report($tutor_id, $tag_id,EntityManagerInterface $em,ParameterBagInterface $params)
    {
        $session = $this->get('session');

        $ay = $session->get('academic_year');

        $repository=$em->getRepository(SPStudentAssignment::class);
        $filter = array();
        $filter['sp_assignment_academic_year']=$ay;
        if ($tag_id>0) $filter['sp_assignment_tag']=$tag_id;
        $allocations = $repository->findBy($filter,['sp_assignment_school'=>'ASC']);
        $pdf = new PDFController();
        foreach ($allocations as $k=>$a) //FILTER DOWN THE ALLOCATIONS
        {
            $tutors = $a->getSpAssignmentTutors();

            //$rm=true;
            foreach ($tutors as $l=>$t) if($t!=NULL)
            {
                if ($t==$tutor_id || $tutor_id==0) $pdf->sp_tutor_report_to_pdf($t,$a->getSpAssignmentTag()->getId(),$params);//$rm=false;
            }
            //if ($rm) unset($allocations[$k]);
        }


        return new Response("OK");
    }

    /**
     * @Route("sp/schools/{school_id}/reports/{period_id}/download")
     */

    public function download_sp_school_report($school_id, $period_id, EntityManagerInterface $em,\Knp\Snappy\Pdf $snappy)
    {
        $session = $this->get('session');

        $ay = $session->get('academic_year');

        $repository=$em->getRepository(SPSchool::class);
        $school=$repository->find($school_id);

        $repository=$em->getRepository(SPPlacement::class);
        //$placement=$repository->findOneBy(['placement_academic_year'=>$session->get('academic_year'),'placement_tag'=>$tag_id]);

        //$placement=$repository->findOneBy(['placement_academic_year'=>9,'placement_tag'=>$tag_id]);

        $repository=$em->getRepository(SPStudentAssignment::class);
        //$allocations = $repository->findBy(['sp_assignment_academic_year'=>$session->get('academic_year'), 'sp_assignment_tag'=>$tag_id],['sp_assignment_school'=>'ASC']);

        //$allocations = $repository->findBy(['sp_assignment_academic_year'=>$ay, 'sp_assignment_period'=>$period_id,'sp_assignment_school'=>$school_id],['sp_assignment_placement'=>'ASC']);
        $allocations = $repository->findSchoolAssignmentsByDate($ay,$period_id,$school_id);
        $empty_allocations = $repository->findUnallocatedSchoolAssignments($ay,$period_id,$school_id);

        $core = new CoreObjects($em);

        foreach ($allocations as $k=>$a)
        {
            if ($a->getSpAssignmentPlacement()->getPlacementPaired() && (strlen($a->getSpAssignmentTeacher())==0 || $a->getSpAssignmentTeacher()==null)) unset($allocations[$k]);
        }

        foreach ($allocations as $k=>$a)
        {
            if ($a->getSpAssignmentPlacement()->getPlacementPaired() && strlen($a->getSpAssignmentTeacher())>0)
            {
                $pairing = array();
                foreach ($allocations as $a_id=>$a_data)
                {

                    if ($a_data->getSpAssignmentTeacher()==$a->getSpAssignmentTeacher() && $a->getSpAssignmentSchool()==$a_data->getSpAssignmentSchool() && $a_data->getSpAssignmentPeriod()==$a->getSpAssignmentPeriod() && $a->getSpAssignmentAcademicYear()==$a_data->getSpAssignmentAcademicYear() && $a_data->getSpAssignmentTag()==$a->getSpAssignmentTag())
                    {
                        $pairing[]=$a_data->getSpAssignmentStudent();
                        if ($a_data->getSpAssignmentStudent()!=$a->getSpAssignmentStudent())
                        {
                            $allocations[$a_id]->pairing=$pairing;
                            unset($allocations[$k]);
                            break;
                        }

                    }

                }

            }

        }


        foreach ($allocations as $a)
        {
            $translated_classes = array();
            foreach ($a->getSpAssignmentClass() as $c)
            {
                $translated_classes[]=$core->translate_internal_value("sp_class",$c);
            }
            $a->translated_classes=$translated_classes;


        }

        foreach ($empty_allocations as $a)
        {
            $translated_classes = array();
            foreach ($a->getSpAssignmentClass() as $c)
            {
                $translated_classes[]=$core->translate_internal_value("sp_class",$c);
            }
            $a->translated_classes=$translated_classes;


        }

        $sname = $school->getSPSchoolName();
        $sname = str_replace("'","",$sname);
        $sname = str_replace(" ","_",$sname);
        $sname = str_replace("í","i",$sname);
        $sname = str_replace("Í","I",$sname);
        $sname = str_replace("é","e",$sname);
        $sname = str_replace("É","E",$sname);
        $sname = str_replace("á","a",$sname);
        $sname = str_replace("Á","A",$sname);
        $sname = str_replace("ó","o",$sname);
        $sname = str_replace("Ó","O",$sname);
        $sname = str_replace("ú","u",$sname);
        $sname = str_replace("Ú","U",$sname);

        $period_name="";

        if ($period_id==0) {$period_name="Unset"; $fperiod="Unset";}
        if ($period_id==1) {$period_name="Fómhair"; $fperiod="Fomhair";}
        if ($period_id==2) {$period_name="Earrach"; $fperiod="Earrach";}
        if ($period_id==3) {$period_name="Summer"; $fperiod="Summer";}
        //dd($allocations);

        $html=$this->renderView('sp/reports/sp_school_report.html.twig',['allocations'=>$allocations,'unallocated'=>$empty_allocations,'school'=>$school,'period'=>$period_id, 'period_name'=>$period_name, 'ay'=>$ay]);

        $snappy->setBinary('/usr/local/bin/wkhtmltopdf');
        $snappy->setOption('orientation', 'portrait');
        $snappy->setOption('enable-local-file-access',true);

        //header('Content-Type: application/pdf');
        //echo $snappy->getOutputFromHtml($html);

        return new PdfResponse(
            $snappy->getOutputFromHtml($html),
            'S'. $sname .'_'.$fperiod.'.pdf'
        );
    }

    /**
     * @Route("sp/schools/{school_id}/reports/{period_id}")
     */

    public function generate_sp_school_report($school_id, $period_id, EntityManagerInterface $em)
    {
        $session = $this->get('session');

        $ay = $session->get('academic_year');

        $repository=$em->getRepository(SPSchool::class);
        $school=$repository->find($school_id);

        $repository=$em->getRepository(SPPlacement::class);
        //$placement=$repository->findOneBy(['placement_academic_year'=>$session->get('academic_year'),'placement_tag'=>$tag_id]);

        //$placement=$repository->findOneBy(['placement_academic_year'=>9,'placement_tag'=>$tag_id]);

        $repository=$em->getRepository(SPStudentAssignment::class);
        //$allocations = $repository->findBy(['sp_assignment_academic_year'=>$session->get('academic_year'), 'sp_assignment_tag'=>$tag_id],['sp_assignment_school'=>'ASC']);

        //$allocations = $repository->findBy(['sp_assignment_academic_year'=>$ay, 'sp_assignment_period'=>$period_id,'sp_assignment_school'=>$school_id],['sp_assignment_placement'=>'ASC']);
        $allocations = $repository->findSchoolAssignmentsByDate($ay,$period_id,$school_id);
        $empty_allocations = $repository->findUnallocatedSchoolAssignments($ay,$period_id,$school_id);

        $core = new CoreObjects($em);

        foreach ($allocations as $a)
        {
            $translated_classes = array();
            foreach ($a->getSpAssignmentClass() as $c)
            {
                $translated_classes[]=$core->translate_internal_value("sp_class",$c);
            }
            $a->translated_classes=$translated_classes;


        }

        foreach ($empty_allocations as $a)
        {
            $translated_classes = array();
            foreach ($a->getSpAssignmentClass() as $c)
            {
                $translated_classes[]=$core->translate_internal_value("sp_class",$c);
            }
            $a->translated_classes=$translated_classes;


        }


        $period_name="";

        if ($period_id==0) $period_name="Unset";
        if ($period_id==1) $period_name="Fómhair";
        if ($period_id==2) $period_name="Earrach";
        if ($period_id==3) $period_name="Summer";
        //dd($allocations);

        return $this->render('sp/reports/sp_school_report.html.twig',['allocations'=>$allocations,'unallocated'=>$empty_allocations,'school'=>$school,'period'=>$period_id, 'period_name'=>$period_name, 'ay'=>$ay]);
    }


    /**
     * @Route("sp/finance_report/{period_id}")
     */

    public function finance_sp_report($period_id, EntityManagerInterface $em)
    {
        $session = $this->get('session');

        $users = $em->getRepository(User::class);

        $repository = $em -> getRepository(SPStudentAssignment::class);
        $assignments = $repository->findby(['sp_assignment_academic_year'=> $session->get('academic_year'), 'sp_assignment_period'=>$period_id]);

        $t_alloc = array();

        foreach ($assignments as $k=>$a)
        {
            if ($a->getSpAssignmentStudent()==NULL) unset($assignments[$k]);
            else
            {
                $output=array();
                $tutors = $a->getSpAssignmentTutors();
                if ($tutors!=NULL) foreach ($tutors as $key=>$id)  if ($id!=NULL && $id!="")
                {
                    $user = $users->find($id);

                    if (!$user)
                    {
                        //throw $this->createNotFoundException(sprintf("User ID [%s] not found",$id," Alloc Id ",$a->getId()));
                    }
                    else {
                        $output[$key] = $user;
                    }
                }
                else $output=NULL;
                $t_alloc[$a->getId()]=$output;
                unset($output);
            }

        }

        return $this->render('sp/reports/sp_finance_report.html.twig',['assignments'=>$assignments,'period'=>$period_id,'t_alloc'=>$t_alloc]);
    }



    /**
     * @Route("sp/documents")
     */
    public function render_sp_documents(EntityManagerInterface $em)
    {
        return $this->render('sp/school_placement_files.html.twig');
    }

    /**
     * @Route("sp/test_pdf")
     */
    public function test_pdf(EntityManagerInterface $em, \Knp\Snappy\Pdf $snappy)
    {
        $html=$this->renderView('test.html.twig');

        if(true)
        {
            $snappy->setBinary('/usr/local/bin/wkhtmltopdf');
            $snappy->setTimeout(600);
            $snappy->setOption('orientation', 'portrait');
            $snappy->setOption('javascript-delay', '1000');
            $snappy->setOption('footer-font-size', 8);
            $snappy->setOption('no-stop-slow-scripts', true);
            $snappy->setOption('enable-local-file-access', true);
            $snappy->setOption('encoding', 'UTF-8');

            return new PdfResponse(
                $snappy->getOutputFromHtml($html),
                'test.pdf'
            );
        }
        else
            return new Response("OK");

    }


}