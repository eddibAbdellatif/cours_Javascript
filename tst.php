<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require(APPPATH . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . 'PDF.php');

class CommandeTFI extends CI_Controller
{

    /**
     * Method by default of the controlller
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('CommandeTFIModel');
        $this->load->model('ProduitModel');
        $this->load->model('DepotFournisseurModel');
        $this->load->model('FournisseurModel');
        $this->load->model('DepotModel');
        $this->load->model('AdressesCLRModel');
        $this->load->model('PackModel');
        $this->load->model('UtilisateurModel');
        $this->load->model('LivrerModel');
        $this->load->model('UserAdressesModel');
        $this->load->model('CommandeProduitRefuseModel');
        $this->load->model('StockCLRModel');
        $this->load->model('CategorieModel');
        $this->load->model('CLRColisModel');
        $this->load->model('TailleVetementModel');
        $this->load->model('ColisModel');
        $this->load->model('ProduitCategorieTailleModel');
        $this->load->model('BuckupModel');
        $this->load->helper(array('form', 'url', 'user_helper'));
        $this->load->library(array('session', 'form_validation', 'UPS','image_lib'));
        require_once realpath(__DIR__ . '/../..') . "/equipements/PHPExcel.php";
        require_once realpath(__DIR__ . '/../..') . "/equipements/PHPExcel/IOFactory.php";
    }

    public function ajouterCommandePL()
    {
        var_dump($this->ProduitModel->produitLogistique());
    }

    public function listeCommandePL($user, $validation = null, $epi_outillage = null)
    {
        $this->load->view('CommandeTFI/commande_pl', array("user" => $user, "validation" => $validation, "epi_outillage" => $epi_outillage));
    }

    public function listeCommandeRexel($idtfi)
    {

        $userId = null;
        $depot = null;
        if ((has_permission(CDT_PROFILE) || has_permission(CDP_PROFILE)) && !has_permission(ADMIN_PROFILE)) {
            $userId = $this->user->getUser();
            $depot_ups = $this->DepotModel->getIdDepotByUser($idtfi);
            $depot_clr = $this->UserAdressesModel->getAdresseCLRUser($idtfi);
        }
        $countCommandeUser = count($this->CommandeTFIModel->count_commande_rexel($idtfi));

        $liste_CommandeTFI = 1;
        $this->db->where("id_user", $idtfi);
        $q = $this->db->get("commande_tfi");


        $usertfi = $this->UtilisateurModel->checkUserById($idtfi);

        if(has_permission(ASL_PROFILE) && !has_permission(ADMIN_PROFILE)){
            $vue="liste_asl";
        }else{
            $vue = getProfileLabel();
        }

        if ($liste_CommandeTFI) {
            $this->load->view('CommandeTFI/' . $vue, [
                    "mes_commandes_cdt" => false,
                    "mes_commandes_cdp" => false,
                    "usertfi" => $usertfi,
                    "commande_rexel" => true,
                    "depot_ups" => $depot_ups,
                    "depot_clr" => $depot_clr,
                    "countCommande" => $countCommandeUser,
                    "idtfi" => $idtfi,
                    "idcdt" => $userId,
                    "id_statut" => null
                ]
            );
        } else
            show_404();
    }

    public function listeCommandePLValidation($user, $validation = null, $epi_outillage = null)
    {
        $sup = $this->UtilisateurModel->getSuperviseurTFI($this->session->userdata("user_id"));
        $sup = $sup->superviseur;
        $cdp = $this->UtilisateurModel->getUser($sup);
        if($cdp->user_vacances==1){
            $this->load->view('CommandeTFI/commande_pl', array("user" => $user, "validation" => $validation, "epi_outillage" => $epi_outillage));
        }else{
            $this->load->view('CommandeTFI/nobuckup');
        }
    }

    public function ajax_listeCommandePL($user, $epi_outillage = null)
    {
        $time_start = microtime(true);
        if (empty($user)) {
            $userId = $this->user->getUser();
        } else {
            $userId = $user;
        }

        $_POST["epi_outillage"] = $epi_outillage;
        $columns = array(
            0 => 'cpl.reference_pl',
            1 => 'cpl.id_cmd_pl',
            2 => 'cpl.date_creation',
            3 => 'tfi.nom',
            4 => 'cps.label',
            5 => 'id_upr.nom',
            6 => 'tc.nom'

        );
        $limit = $this->input->post('length');
        $start = $this->input->post('start');

        $order = $columns[$this->input->post('order')[0]['column']];
        $dir = $this->input->post('order')[0]['dir'];

        //var_dump($this->CommandeTFIModel->count_liste_commande_pl($userId));

        $totalData = $this->CommandeTFIModel->count_liste_commande_pl($userId);


        $totalFiltered = $totalData;
        if (empty($this->input->post('search')['value'])) {
            $commandes = $this->CommandeTFIModel->getAllCommande_pl($limit, $start, $order, $dir, $userId);
        } else {
            $data_search = array('search' => $this->input->post('search')['value']);

            $commandes = $this->CommandeTFIModel->getAllCommande_pl($limit, $start, $order, $dir, $userId, $data_search);

            $totalFiltered = $this->CommandeTFIModel->count_liste_commande_pl($userId, $data_search);
        }
        $dataview = array();

        if (!empty($commandes)) {
            foreach ($commandes as $commande) {
                $row = array();
                $row['reference'] = $commande->reference_pl;
                $row['user'] = $commande->user . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $commande->user_sup;
                $row['id'] = $commande->id_cmd_pl;
                $row['date_creation'] = date_fr($commande->date_creation);

                $row['cree_par'] = $commande->reference_pl .
                    "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $commande->reference_pl;
                $row['etat'] = $commande->label;
                $row['type_commande'] = $commande->type_commande;
                $row['upr'] = (($commande->nom_upr) ? $commande->nom_upr : "---");
                if($userId!=$this->user->getUser()){
                    $row['action'] = '<a class="btn btn-default" href="' . site_url('CommandeTFI/detail_pl_buckup/' . $commande->id_cmd_pl) . (($epi_outillage) ? "/epi_outillage" : "") . '"><i class="fa fa-eye"></i></a>';
                }else{
                    $row['action'] = '<a class="btn btn-default" href="' . site_url('CommandeTFI/detail_pl/' . $commande->id_cmd_pl) . (($epi_outillage) ? "/epi_outillage" : "") . '"><i class="fa fa-eye"></i></a>';
                }
                $dataview[] = $row;
            }

        }
        $time_stop = microtime(true) - $time_start;
        $json_data = array(
            "draw" => intval($this->input->post('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $dataview,
            "time" => number_format($time_stop,2,".","").' sec'
        );

        echo json_encode($json_data);

    }

    private function randStrGen($len)
    {
        $result = "";
        $chars = "abcdefghijklmnopqrstuvwxyz_-0123456789";
        $charArray = str_split($chars);
        for ($i = 0; $i < $len; $i++) {
            $randItem = array_rand($charArray);
            $result .= "" . $charArray[$randItem];
        }
        return $result;
    }

    private function trierListeProduits(&$listeProduits)
    {
        for ($i = 0; $i < count($listeProduits) - 1; $i++) {
            $min = $i;

            for ($j = $i + 1; $j < count($listeProduits); $j++) {
                if (intval($listeProduits[$min]['id_produit']) > intval($listeProduits[$j]['id_produit']))
                    $min = $j;
            }

            if ($min != $i) {
                $temp = $listeProduits[$min];
                $listeProduits[$min] = $listeProduits[$i];
                $listeProduits[$i] = $temp;

            }
        }
    }

    private function addExtratCountField(&$listeProduits)
    {
        $id_precedent_produit = $listeProduits[0]['id_produit'];
        $compteur = 1;
        for ($i = 1; $i < count($listeProduits); $i++) {
            if ($id_precedent_produit == $listeProduits[$i]['id_produit'])
                $compteur++;
            else {
                $listeProduits[$i - 1]['count'] = $compteur;
                $compteur = 1;
                $id_precedent_produit = $listeProduits[$i]['id_produit'];
            }
        }
        $listeProduits[$i - 1]['count'] = $compteur;
    }

    public function generatePDF($id, $id_livree = null)
    {

        if ($id != null && !empty($id)) {

            $pdf = new PDF();
            $pdf->AliasNbPages();
            $pdf->AddPage();
            $header = array('Réf', 'Désignation', 'N° de série', 'Quantité');
            $pdf->SetFont('Arial', '', 10);
            $bl_header = $this->CommandeTFIModel->getBonLivraisonHeader($id);
            $mode_livraison = $this->LivrerModel->findLivreeCmd($bl_header->id_cmd);
            $temp = $this->CommandeTFIModel->getBonLivraisonBody($id);
            $bl_body = array();
            $path = realpath(__DIR__ . '/../../') . "/colisimages/";
            $filename =  $path . $id . ".jpg";
            $imageColis=false;
            if (is_file($filename))
            {
                $imageColis=true;
            }
            foreach ($temp as $ligne)
                $bl_body[] = [$ligne->reference_free, $ligne->article, (($ligne->reference && !empty($ligne->reference) && $ligne->id_categorie != ID_PRODUIT_EPISPE) ? $ligne->reference : '---'), $ligne->quantite];
            if (($mode_livraison->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $mode_livraison->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS || $mode_livraison->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) && $imageColis==true) {
                $pdf->bonLivraison(["bon_livraison" => $bl_header->reference, "date_expedition" => $bl_header->date_expedition,
                    "code_tracking" => $bl_header->code_tracking, 'prepare_par' => $bl_header->prepare_par,
                    'destinataire' => $bl_header->destinataire, "code_barre" => $path . $id . ".jpg","mode_livrasion"=>$mode_livraison->type_livree], $header, $bl_body);
            } else {
                $pdf->bonLivraison(["bon_livraison" => $bl_header->reference, "date_expedition" => $bl_header->date_expedition,
                    "code_tracking" => $bl_header->code_tracking, 'prepare_par' => $bl_header->prepare_par,
                    'destinataire' => $bl_header->destinataire,"mode_livrasion"=>$mode_livraison->type_livree], $header, $bl_body);
            }

            $pdf->Output();
        } else
            show_404();
    }


    public function generatePDFMulti($id)
    {

        if ($id != null && !empty($id)) {
            $all_cmd = $this->CommandeTFIModel->getOtherColisCmd($id);
            //   var_dump($all_cmd);
            //     die();
            $referances = "\n";
            foreach ($all_cmd as $key => $cmd) {
                $countproduits = $this->CommandeTFIModel->countProduitCmd($cmd->id_cmd)->qte_produit_cmd;
                $countproduitscolis = $this->CommandeTFIModel->countProduitOneColis($cmd->id_colis)->qte_produit_colis;
                $referances .= $cmd->reference . "    ($countproduitscolis/" . $countproduits . ' produits)';
                if ($key != count($all_cmd) - 1)
                    $referances .= "\n";
                else {
                    $referances .= "";
                }
            }
            $pdf = new PDF();
            $pdf->AliasNbPages();
            $pdf->AddPage();
            $header = array('Réf', 'Désignation', 'N° de série', 'Quantité');
            $pdf->SetFont('Arial', '', 10);
            $bl_header = $this->CommandeTFIModel->getBonLivraisonHeader($id);
            $mode_livraison = $this->LivrerModel->findLivreeCmd($bl_header->id_cmd);
            $temp = $this->CommandeTFIModel->getBonLivraisonBody($cmd->id_parent);
            //   var_dump($temp, $bl_header, $mode_livraison, $all_cmd , $referances);
            //  die();
            $bl_body = array();
            $path = realpath(__DIR__ . '/../../') . "/colisimages/";
            foreach ($temp as $ligne)
                $bl_body[] = [$ligne->reference_free, $ligne->article, (($ligne->reference && !empty($ligne->reference) && $ligne->id_categorie != ID_PRODUIT_EPISPE) ? $ligne->reference : '---'), $ligne->quantite];
            if ($mode_livraison->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $mode_livraison->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS || $mode_livraison->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
                $pdf->bonLivraison(["bon_livraison" => $referances, "date_expedition" => $bl_header->date_expedition,
                    "code_tracking" => $bl_header->code_tracking, 'prepare_par' => $bl_header->prepare_par,
                    'destinataire' => $bl_header->destinataire, "code_barre" => $path . $id . ".jpg"], $header, $bl_body, true);
            } else {
                $pdf->bonLivraison(["bon_livraison" => $referances, "date_expedition" => $bl_header->date_expedition,
                    "code_tracking" => $bl_header->code_tracking, 'prepare_par' => $bl_header->prepare_par,
                    'destinataire' => $bl_header->destinataire], $header, $bl_body, true);
            }

            $pdf->Output();
        } else
            show_404();
    }

    public function index()
    {
        $this->liste();
    }

    public function listecommande($id_etat_colis = null)
    {
        $Etats_commande = $this->CommandeTFIModel->listeStatutsCommande();
        if (has_permission(TFI_PROFILE))
            $users = array();
        else
            $users = $this->UtilisateurModel->listeuser($this->user->getUser());
        //$categories = $this->CategorieModel->getAllCategories();
        $categories = $this->CategorieModel->getAll();

        $cdp = $this->BuckupModel->GetAllCdp2();
        $currentUser = $this->UtilisateurModel->getUser($this->user->getUser());
        $this->load->view('CommandeTFI/recherche_commande', ['Etats_commande' => $Etats_commande, 'id_etat_colis' => $id_etat_colis, "destinataires" => $users, "currentUser" => $currentUser, "categories" => $categories, "cdp" => $cdp]);
    }

    public function liste_commande_ajax()
    {
        $time_start = microtime(true);
        $data = $this->input->post();
        $userId = $this->user->getUser();
        $columns = array(
            0 => 'c.reference',
            1 => 'c.date_creation',
            2 => 'u2.nom',
            3 => 'u.nom',
            4 => 'libelle',
            5 => 'type_commande'

        );
        $limit = $this->input->post('length');
        $start = $this->input->post('start');

        $order = $columns[$this->input->post('order')[0]['column']];
        $dir = $this->input->post('order')[0]['dir'];
        $filtre1 = substr($this->input->post('columns')[2]['search']['value'], 1, -1);

        $totalData = count($this->CommandeTFIModel->get_all_cmd_cls_log(0, 0, $order, $dir, $userId, $filtre1));

        $totalFiltered = $totalData;

        if (isset($data['id_cdp']))
            $id_cdp = $data['id_cdp'];
        else
            $id_cdp = false;
        if (isset($data['id_produit']))
            $id_produit = $data['id_produit'];
        else
            $id_produit = false;
        if (isset($data['id_categorie']))
            $id_categorie = $data['id_categorie'];
        else
            $id_categorie = false;



        if (empty($this->input->post('search')['value']) && empty($data['id_produit']) && empty($data['id_categorie']) && empty($id_cdp)) {
            //$users = $this->CommandeTFIModel->getAllCommande($limit, $start, $order, $dir, $userId, $filtre1);

            $users = $this->CommandeTFIModel->get_all_cmd_cls_log($limit, $start, $order, $dir, $userId, $filtre1);

        } else {
            $data_search = array('search' => $this->input->post('search')['value'], 'id_produit' => $id_produit, 'id_categorie' => $id_categorie, 'id_cdp' => $id_cdp);

            //$users = $this->CommandeTFIModel->getAllCommande($limit, $start, $order, $dir, $userId, $filtre1, $data_search);

            $users = $this->CommandeTFIModel->get_all_cmd_cls_log($limit, $start, $order, $dir, $userId, $filtre1, $data_search);

            $filer = count($this->CommandeTFIModel->get_all_cmd_cls_log(0, 0, $order, $dir, $userId, $filtre1, $data_search));

            $totalFiltered = $filer;

            // $this->CommandeTFIModel->count_liste_commande($userId, $filtre1, $data_search);
        }
        $dataview = array();
        $num = "";

        if (!empty($users)) {
            foreach ($users as $colis) {
                $row = array();
                $row['reference'] = $colis->reference;
                $row['date_creation'] = date_fr($colis->date_creation);

                $row['cree_par'] = $colis->cree_par .
                    "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $colis->nom_cdt_sup;
                $row['destinataire'] = $colis->destinataire . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $colis->nom_tfi_sup;

                $row['etat'] = $colis->libelle;
                $row['type_commande'] = $colis->type_commande;

                if(in_array($colis->type_commande, array('Logistique','Aérien'))) {
                    $row['bl'] = '<a class="btn btn-default" href="' . site_url('CommandeTFI/detail_pl/' . $colis->id_cmd) . '"><i class="fa fa-eye"></i></a>';
                }

                elseif ($colis->type_commande == 'epi_outillage') {

                    $row['bl'] = '<a class="btn btn-default" href="' . site_url('CommandeTFI/detail_pl/' . $colis->id_cmd.'/epi_outillage') . '"><i class="fa fa-eye"></i></a>';

                }
                else {
                    $row['bl'] = '<a class="btn btn-default" href="' . site_url('CommandeTFI/detail/' . $colis->id_cmd) . '"><i class="fa fa-eye"></i></a>';
                }

                $dataview[] = $row;
            }

        }

        // $totalData = $this->CommandeTFIModel->count_liste_commande($userId, $filtre1);
        //$totalFiltered = $totalData;

        $time_stop = microtime(true) - $time_start;
        $json_data = array(
            "draw" => intval($this->input->post('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $dataview,
            "time" => number_format($time_stop,2,".","").' sec'
        );

        echo json_encode($json_data);

    }


    public function commandeauto()
    {
        if (date('d') == 1 || date('d') == 15) {
            foreach ($this->UtilisateurModel->Liste_TFI() as $user) {
                $this->CommandeTFIModel->commandeauto($user->id_user);
            }
        }
    }

    public function data($id_cmd)
    {
        $count_produitcmd = $this->CommandeTFIModel->countProduitCmd($id_cmd);
        $count_produitcolis = $this->CommandeTFIModel->countProduitColis($id_cmd);
        var_dump($count_produitcmd->qte_produit_cmd);
        var_dump($count_produitcolis->qte_produit_colis);
    }

    public function ajax_list_arg()
    {
        $time_start = microtime(true);
        $data = $this->input->post();

        if (isset($data['id_statut'])) {
            $id_statut = $data['id_statut'];
            $dest = $data['dest'];

            $result = $this->CommandeTFIModel->get_datatables_arg($id_statut, $data);

            $list = $result['liste'];
            $result_data = array();
            $no = $_POST['start'];
            if (count($list) > 0)
                foreach ($list as $cmd) {
                    $no++;
                    $row = array();
                    $row['num'] = $no;
                    $row['date_creation'] = $cmd->date_creation;
                    if ($id_statut == VALIDATED || $id_statut == MISSING_STOCK || $id_statut == 100) {
                        $row['reference'] = "<button style='margin-left: -9px;margin-right: 10px;' class='fa fa-plus btn btn-success detail'  id='" . $cmd->id . "'></button>" . $cmd->reference;
                    } else {
                        $row['reference'] = $cmd->reference;
                    }

                    $row['creee_par'] = $cmd->nom_cdt . ' ' . $cmd->prenom_cdt
                        . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                        . $cmd->nom_cdt_sup;
                    $row['destinataire'] = $cmd->nom_tfi . ' ' . $cmd->prenom_tfi .
                        "<br/><span style='font-size:13px;color:#777;'>Sup: "
                        . $cmd->nom_tfi_sup;
                    if ($cmd->vacances_tfi == 0)
                        $row['destinataire'] = $cmd->nom_tfi . ' ' . $cmd->prenom_tfi .
                            "<br/><span style='font-size:13px;color:#777;'>Sup: "
                            . $cmd->nom_tfi_sup;
                    elseif ($cmd->vacances_tfi == 1) {
                        $row['destinataire'] = $cmd->nom_tfi . ' ' . $cmd->prenom_tfi . "<br> <font color='red'>En congés </font></br>" .
                            "<span style='font-size:13px;color:#777;'>Sup: "
                            . $cmd->nom_tfi_sup;
                    }

                    if ($id_statut == RELIQUAT_SLA || $id_statut == CMD_PREPARER || $id_statut == SHIPPED || $id_statut == LIVRE || $id_statut == RECEIVED) {
                        $row['date_validation'] = $cmd->date_validation;
                        $row['valider_par'] = $cmd->nom_validation . ' ' . $cmd->prenom_validation;
                    } else if ($id_statut == VALIDATED) {
                        $row['date_validation'] = $cmd->date_last_action;
                        $row['valider_par'] = $cmd->nom_last_action . ' ' . $cmd->prenom_last_action;
                    }
                    if ($id_statut == LIVRE || $id_statut == RECEIVED) {
                        $row['date_expedition'] = $cmd->date_expedition;
                    } elseif ($id_statut == SHIPPED) {
                        $row['date_expedition'] = $cmd->date_last_action;
                    }
                    if ($id_statut == RECEIVED) {
                        $row['date_reception'] = $cmd->date_last_action;
                    }
                    $row['etat'] = $cmd->etat_cmd;


                    if ($id_statut == WAIT_VALIDATION_AGR && has_permission(ARG_PROFILE)) {
                        $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($cmd->id);
                        $i = 0;
                        $j = 0;
                        $k = 0;
                        foreach ($liste_commande_produit as $produit) {
                            $dispo = (($produit->stock_arg - $produit->stock_virtuel) - ($produit->quantite - ((!$produit->quantite_expediee) ? 0 : $produit->quantite_expediee)));
                            if ($dispo < 0) {
                                $i++;
                            }
                            if ($dispo > 0) {
                                $j++;
                            }
                            $k++;
                        }

                        if ($k != 0 && ((($j / $k) * 100) <= 50)) {
                            $row['stock_dispo'] = '<div class="progress">
                                              <div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow=".round(($j/$k)*100)." aria-valuemax="100" style="width:' . round(($j / $k) * 100) . '%">
                                              </div>
                                              </div> ';
                            $row['stock_ordre'] = 0;
                        } else if ($k != 0 && ((($j / $k) * 100) > 50) && ((($j / $k) * 100) < 99)) {
                            $row['stock_dispo'] = '<div class="progress">
                                                <div class="progress-bar progress-bar-info" role="progressbar" aria-valuenow=".round(($j/$k)*100)." aria-valuemax="100" style="width:' . round(($j / $k) * 100) . '%">
                                                </div>
                                                </div> ';
                            $row['stock_ordre'] = 1;
                        } else if ($k != 0 && ((($j / $k) * 100) > 99)) {
                            $row['stock_dispo'] = '<div class="progress">
                                                <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow=".round(($j/$k)*100)." aria-valuemax="100" style="width:' . round(($j / $k) * 100) . '%">
                                                </div>
                                                </div> ';
                            $row['stock_ordre'] = 2;
                        }
                    }
                    if ($id_statut == LIVRE || $id_statut == RECEIVED) {
                        $cmd->colis = $this->CommandeTFIModel->getTotalColisByCmd($cmd->id)->total;
                        $cmd->colis_recus = $this->CommandeTFIModel->getTotalColisRecusByCmd($cmd->id)->total_recus;
                        $row['colis_recus'] = $cmd->colis_recus . ' / ' . $cmd->colis;
                    }

                    $row['action'] = "<a class='btn btn-default' href='" . site_url('CommandeTFI/detail/' . $cmd->id) . "'><i class='fa fa-eye'></i></a>";

                    $result_data[] = $row;
                }
            if ($id_statut == WAIT_VALIDATION_AGR && has_permission(ARG_PROFILE)) {

                for ($i = 0; $i < count($result_data) - 1; $i++) {
                    for ($j = $i + 1; $j < count($result_data); $j++) {
                        if ($result_data[$j]['stock_ordre'] > $result_data[$i]['stock_ordre']) {
                            $tmp = $result_data[$i];
                            $result_data[$i] = $result_data[$j];
                            $result_data[$j] = $tmp;
                        }
                    }
                }
            }
            if (isset($_POST["order"][0]["column"])) {
                if ($_POST["order"][0]["column"] == 5) {
                    if ($_POST["order"][0]["dir"] == "asc") {
                        if ($id_statut == WAIT_VALIDATION_AGR && has_permission(ARG_PROFILE)) {

                            for ($i = 0; $i < count($result_data) - 1; $i++) {
                                for ($j = $i + 1; $j < count($result_data); $j++) {
                                    if ($result_data[$j]['stock_ordre'] < $result_data[$i]['stock_ordre']) {
                                        $tmp = $result_data[$i];
                                        $result_data[$i] = $result_data[$j];
                                        $result_data[$j] = $tmp;
                                    }
                                }
                            }
                        }
                    } else {
                        if ($id_statut == WAIT_VALIDATION_AGR && has_permission(ARG_PROFILE)) {

                            for ($i = 0; $i < count($result_data) - 1; $i++) {
                                for ($j = $i + 1; $j < count($result_data); $j++) {
                                    if ($result_data[$j]['stock_ordre'] > $result_data[$i]['stock_ordre']) {
                                        $tmp = $result_data[$i];
                                        $result_data[$i] = $result_data[$j];
                                        $result_data[$j] = $tmp;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $time_stop = microtime(true) - $time_start;
            $output = array("draw" => $_POST['draw'], "recordsTotal" => $this->CommandeTFIModel->count_all_arg($id_statut, $data),
                "recordsFiltered" => $this->CommandeTFIModel->count_filtered_arg($id_statut, $data), "data" => $result_data, "query" => $result['query'],"time"=>number_format($time_stop,2,".","").' sec');

            echo json_encode($output);
        } else
            echo null;
    }

    /**
     * Methode qui permet de lister tous les commandes TFI du CDT connecté
     */
    public function liste($id_statut = null)
    {
        if(has_permission(ASL_PROFILE) && !has_permission(ADMIN_PROFILE)){
            $vue="liste_asl";
        }else{
            $vue = getProfileLabel();
        }

        $users = $this->UtilisateurModel->listeuser($this->user->getUser());
        $currentUser = $this->UtilisateurModel->getUser($this->user->getUser());
        //si Mes Commandes de CDT
        $mes_commandes_cdt = false;
        if (!has_permission(ADMIN_PROFILE) && has_permission(CDT_PROFILE))
            $mes_commandes_cdt = true;
        //si Mes Commandes de CDT
        $mes_commandes_cdp = false;
        if (!has_permission(ADMIN_PROFILE) && has_permission(CDP_PROFILE))
            $mes_commandes_cdp = true;

        if (has_permission(ARG_PROFILE) && $id_statut == WAIT_VALIDATION_AGR) {
            $week = date("W");
            $semaine = $this->CommandeTFIModel->nbrCommandeByWeek();
            $option = "";
            foreach ($semaine as $s) {
                $option .= "<option value='" . $s->week . "'" . (($week == $s->week) ? 'selected' : '') . ">S-" . $s->week . "</option>";
            }

        }

        $upr = $this->CommandeTFIModel->getAllUpr();
        $buckup_cdp = false;
        $cdp = false;
        $sup = $this->UtilisateurModel->getSuperviseurTFI($this->session->userdata("user_id"));
        $sup = $sup->superviseur;
        $cdp = $this->UtilisateurModel->getUser($sup);

        if ($id_statut == BUCKUP_CDP && $this->BuckupModel->isBuckupofCDP()->count == 0) {
            $this->load->view('CommandeTFI/nobuckup');

        } else if ($id_statut == RELIQUAT_SLA) {
            if (isset($option)) {
                $this->load->view('CommandeTFI/' . $vue, [
                        "mes_commandes_cdt" => $mes_commandes_cdt,
                        "mes_commandes_cdp" => $mes_commandes_cdp,
                        "id_statut" => $id_statut,
                        'option' => $option,
                        'users' => $users,
                        'currentUser' => $currentUser,
                        'upr' => $upr,
                    ]
                );
            } else {
                $this->load->view('CommandeTFI/' . $vue, [
                        "mes_commandes_cdt" => $mes_commandes_cdt,
                        "mes_commandes_cdp" => $mes_commandes_cdp,
                        "id_statut" => $id_statut,
                        "categories" => $this->CategorieModel->listeCategories(),
                        'users' => $users,
                        'currentUser' => $currentUser,
                        'upr' => $upr,
                    ]
                );
            }
        } else {
            if (isset($option)) {
                $this->load->view('CommandeTFI/' . $vue, [
                        "mes_commandes_cdt" => $mes_commandes_cdt,
                        "mes_commandes_cdp" => $mes_commandes_cdp,
                        "id_statut" => $id_statut,
                        'option' => $option,
                        "cdp" => $cdp,
                        'users' => $users,
                        'currentUser' => $currentUser,
                        'categories' => $categories = $this->CategorieModel->getAllCategories(),
                        'upr' => $upr,
                    ]
                );
            } else {
                $this->load->view('CommandeTFI/' . $vue, [
                        "mes_commandes_cdt" => $mes_commandes_cdt,
                        "mes_commandes_cdp" => $mes_commandes_cdp,
                        "id_statut" => $id_statut,
                        "cdp" => $cdp,
                        'users' => $users,
                        'currentUser' => $currentUser,
                        'categories' => $categories = $this->CategorieModel->getAllCategories(),
                        'upr' => $upr,
                    ]
                );
            }
        }

    }

    public function list_ajax_slan()
    {
        $time_start = microtime(true);
        $data = $this->input->post();
        $userId = $this->input->post("userid");
        $id_statut = $this->input->post("id_statut");

        if ($id_statut == RELIQUAT_SLA) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => 'h1.date_creation',
                4 => "cdt.nom",
                5 => "tfi.nom",
                6 => "s.libelle",
                7 => "id_upr.nom"
            );
        } else if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == LIVRE) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "tfi.nom",
                5 => "h1.date_creation",
                6 => "u1.nom",
                7 => "h1.commentaire",
                8 => "s.libelle"
            );
        } else if ($id_statut == RECEIVED || $id_statut == SHIPPED) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "tfi.nom",
                5 => "h1.date_creation",
                6 => "u1.nom",
                7 => "h1.commentaire",
                8 => "s.libelle"
            );
        } else if ($id_statut == WAIT_VALIDATION) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "tfi.nom",
                5 => "s.libelle",
                6 => "bar",
                7 => ""
            );
        }else {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "tfi.nom",
                5 => "s.libelle"
            );
        }

        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $order = $columns[$this->input->post('order')[0]['column']];
        $dir = $this->input->post('order')[0]['dir'];
        $filtre1 = substr($this->input->post('columns')[3]['search']['value'], 1, -1);
        $filtre2 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
        if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == LIVRE) {
            $filtre3 = substr($this->input->post('columns')[8]['search']['value'], 1, -1);
        } else if ($id_statut == RELIQUAT_SLA) {
            $filtre1 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
            $filtre2 = substr($this->input->post('columns')[5]['search']['value'], 1, -1);
            $filtre3 = substr($this->input->post('columns')[7]['search']['value'], 1, -1);
        } else if ($id_statut == RECEIVED || $id_statut == SHIPPED) {
            $filtre3 = substr($this->input->post('columns')[7]['search']['value'], 1, -1);
        } else
            $filtre3 = substr($this->input->post('columns')[5]['search']['value'], 1, -1);

        $totalData = $this->CommandeTFIModel->allslancmd_count(null, $id_statut, $filtre1, $filtre2, $filtre3);

        $totalFiltered = $totalData;

        if (empty($this->input->post('search')['value'])) {
            $posts = $this->CommandeTFIModel->allslancmd($limit, $start, $order, $dir, null, $id_statut, $filtre1, $filtre2, $filtre3);
        } else {
            $search = $this->input->post('search')['value'];

            $posts = $this->CommandeTFIModel->allslancmd($limit, $start, $order, $dir, null, $id_statut, $filtre1, $filtre2, $filtre3, $search);

            $totalFiltered = $this->CommandeTFIModel->allslancmd_count(null, $id_statut, $filtre1, $filtre2, $filtre3, $search);
        }

        $data = array();
        if (!empty($posts)) {
            //var_dump($posts);

            foreach ($posts as $post) {
                $nestedData['id'] = $post->id;
                $nestedData['reference'] = $post->reference;
                $nestedData['created_by'] = $post->nom_cdt
                    . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $post->nom_cdt_sup;
                $nestedData['destinataire'] = $post->nom_tfi
                    . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $post->nom_tfi_sup;
                $nestedData['created'] = date('d/m/Y', strtotime($post->date_creation));
                if ( $id_statut == RELIQUAT_SLA || $id_statut == WAIT_VALIDATION) {
                    $nestedData['upr'] = (($post->nom_upr) ? $post->nom_upr : "---");
                }
                $nestedData['etat'] = $post->etat_cmd;
                if ($id_statut == RELIQUAT_SLA || $id_statut == WAIT_VALIDATION) {
                    if (($post->bar * 100) <= 25) {
                        $nestedData['stock_ordre'] = $post->bar * 100;
                        $nestedData['stock_dispo'] = '<div class="progress">
                                              <div class="progress-bar progress-bar-danger" role="progressbar" aria-valuenow=".round(($dispo/$k)*100)." aria-valuemax="100" style="width:' . round($post->bar * 100) . '%"> <span class="bartext">' . round($post->bar * 100) . '%</span>
                                              </div>
                                              </div> ';
                    } else if (($post->bar * 100) > 25 && ($post->bar * 100) <= 50) {
                        $nestedData['stock_ordre'] = $post->bar * 100;
                        $nestedData['stock_dispo'] = '<div class="progress">
                                          <div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow=".round(($dispo/$k)*100)." aria-valuemax="100" style="width:' . round($post->bar * 100) . '%"><span class="bartext">' . round($post->bar * 100) . '%</span>
                                          </div>
                                          </div> ';
                    } else if (($post->bar * 100) > 50 && ($post->bar * 100) <= 75) {
                        $nestedData['stock_ordre'] = $post->bar * 100;
                        $nestedData['stock_dispo'] = '<div class="progress" >
                                        <div class="progress-bar "  role="progressbar" aria-valuenow=".round($dispo)." aria-valuemax="100" style="width:' . round($post->bar * 100) . '%;background:  #FFEB3B;"><span class="bartext">' . round($post->bar * 100) . '%</span>
                                        </div>
                                        </div> ';
                    } else if (($post->bar * 100) > 75) {
                        $nestedData['stock_ordre'] = $post->bar * 100;
                        $nestedData['stock_dispo'] = '<div class="progress">
                                        <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow=".round($dispo)." aria-valuemax="100" style="width:' . round($post->bar * 100) . '%"> <span class="bartext">' . round($post->bar * 100) . '%</span>
                                        </div>
                                        </div> ';
                    }

                }
                $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'>
                                                    <i class=\"fa fa-eye\"></i></a>";

                if ($id_statut == RELIQUAT_SLA) {
                    $nestedData['reliquat'] = date('d/m/Y', strtotime($post->date_reliquat));
                }
                if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == RECEIVED || $id_statut == SHIPPED || $id_statut == LIVRE) {
                    $nestedData['validation'] = date('d/m/Y', strtotime($post->date_validation));
                    $nestedData['validee_par'] = $post->nom_validation . " " . $post->prenom_validation;
                    $nestedData['remarque'] = $post->commentaire_validation;
                }
                if ($id_statut == RECEIVED || $id_statut == SHIPPED) {
                    $post->colis = $this->CommandeTFIModel->getTotalColisByCmd($post->id);
                    $post->colis_recus = $this->CommandeTFIModel->getTotalColisRecusByCmd($post->id);
                    $nestedData['nbr_colis'] = $post->colis_recus->total_recus . "/" . $post->colis->total;
                }
                $data[] = $nestedData;

            }
        }

        $time_stop = microtime(true) - $time_start;

        $json_data = array(
            "draw" => intval($this->input->post('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data,
            "time" => number_format($time_stop,2,".","").' sec'
        );

        echo json_encode($json_data);
    }

    /**
     * Methode qui permet de lister les commandes d'un TFI
     */
    public function listeCommandesTFI($idtfi)
    {

        $userId = null;
        $depot = null;
        if ((has_permission(CDT_PROFILE) || has_permission(CDP_PROFILE)) && !has_permission(ADMIN_PROFILE)) {
            $userId = $this->user->getUser();
            $depot_ups = $this->DepotModel->getIdDepotByUser($idtfi);
            $depot_clr = $this->UserAdressesModel->getAdresseCLRUser($idtfi);
        }
        $countCommandeUser = $this->CommandeTFIModel->countCommandUser($idtfi);

        $liste_CommandeTFI = $this->CommandeTFIModel->listeByTFI($userId, $idtfi);
        $this->db->where("id_user", $idtfi);
        $q = $this->db->get("commande_tfi");
        //var_dump($q->result());
        foreach ($liste_CommandeTFI as $cmd) {
            $cmd->colis = $this->CommandeTFIModel->getTotalColisByCmd($cmd->id);
            $cmd->colis_recus = $this->CommandeTFIModel->getTotalColisRecusByCmd($cmd->id);
        }

        $usertfi = $this->UtilisateurModel->checkUserById($idtfi);

        if(has_permission(ASL_PROFILE) && !has_permission(ADMIN_PROFILE)){
            $vue="liste_asl";
        }else{
            $vue = getProfileLabel();
        }

        if ($liste_CommandeTFI) {
            $this->load->view('CommandeTFI/' . $vue, [
                    "mes_commandes_cdt" => false,
                    "mes_commandes_cdp" => false,
                    "usertfi" => $usertfi,
                    "liste_CommandeTFI" => $liste_CommandeTFI,
                    "depot_ups" => $depot_ups,
                    "depot_clr" => $depot_clr,
                    "countCommande" => $countCommandeUser,
                    "idtfi" => $idtfi,
                    "idcdt" => $userId
                ]
            );
        } else
            show_404();

    }

    /**
     * Methode qui permet d'afficher le detail du commande TFI
     * en argument
     * @param int $id_commande : id du commande
     */
    public function getColis($id_commande)
    {
        echo json_encode([$this->CommandeTFIModel->getTotalColisByCmd($id_commande),
            $this->CommandeTFIModel->getTotalColisRecusByCmd($id_commande)]);
    }

    public function getValid($id_commande)
    {
        var_dump($this->CommandeTFIModel->listeCommandeProduitPlus($id_commande));
    }

    public function detail($id_commande)
    {
        $userId = null;
        if ((has_permission(CDT_PROFILE) || has_permission(CDP_PROFILE) || has_permission(ASL_PROFILE) || has_permission(TFI_PROFILE)) && !has_permission(ADMIN_PROFILE)) {
            $userId = $this->user->getUser();
        }
        $rexel_exist = $this->CommandeTFIModel->getRexelProduitsByCmd($id_commande);
        $status = $this->CommandeTFIModel->getLastStatus($id_commande);
        $commande = $this->CommandeTFIModel->getCommande($id_commande, $userId);
        $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($id_commande);

        $getPack = $this->CommandeTFIModel->getPackCommande($id_commande)->designation;
        $liste_virt_stock = $this->CommandeTFIModel->GetVirtualStock($id_commande);
        $liste_virt_stock_arg = $this->CommandeTFIModel->GetVirtualStock2($id_commande);

        $liste_virt_stock2 = array();
        $liste_virt_stock_arg2 = array();
        if ($liste_virt_stock_arg) {
            foreach ($liste_virt_stock_arg as $value) {
                $liste_virt_stock_arg2[$value->id_produit] = $value;
            }
            $i = 0;
            //       var_dump($liste_virt_stock_arg2);
            foreach ($liste_commande_produit as $value) {
                //  $liste_virt_stock2[$value->id_produit]=$value;

                $liste_commande_produit[$i]->sum_stock_virtuel = ((isset($liste_virt_stock_arg2[$value->id_produit])) ? $liste_virt_stock_arg2[$value->id_produit]->sum_stock_virtuel : 0);
                $i++;
            }
        } else {
            $i = 0;
            foreach ($liste_commande_produit as $value) {
                $liste_commande_produit[$i]->sum_stock_virtuel = 0;
                $i++;
            }
        }

        if ($liste_virt_stock) {
            foreach ($liste_virt_stock as $value) {
                $liste_virt_stock2[$value->id_produit] = $value;
            }
            $i = 0;
            foreach ($liste_commande_produit as $value) {
                $liste_commande_produit[$i]->sum_stock_virtuel_clr = ((isset($liste_virt_stock2[$value->id_produit])) ? $liste_virt_stock2[$value->id_produit]->sum_stock_virtuel_clr : 0);
                $i++;
            }
        } else {
            $i = 0;
            foreach ($liste_commande_produit as $value) {
                $liste_commande_produit[$i]->sum_stock_virtuel_clr = 0;
                $i++;
            }
        }
        foreach ($liste_commande_produit as $key => $cmd) {
            if (!$cmd->quantite || !$cmd) {
                unset($liste_commande_produit[$key]);
                continue;
            }

            $qte_colis = $this->CommandeTFIModel->getQteColisByProd($id_commande, $cmd->id_produit, $cmd->reference);
            $liste_commande_produit[$key]->qte_Reliquats = $this->CommandeTFIModel->getCountProduitRelequit($cmd->id_produit)->somme;
            $liste_commande_produit[$key]->stock_produit = $this->CommandeTFIModel->listeCommandeProduitStockByUser($cmd->id_user, $cmd->id_produit)->stock_produit;
            $stock_tfi = $this->CommandeTFIModel->listeCommandeProduitStockByReference($cmd->id_user, $cmd->id_produit, $cmd->reference);
            $liste_commande_produit[$key]->qte_stock_transit = ($stock_tfi) ? $stock_tfi->qte_stock_transit : 0;

            if (!has_permission(ADMIN_PROFILE) && (has_permission(SLA_PROFILE) || has_permission(SLAN_PROFILE))) {
                $stock_tfi = $this->CommandeTFIModel->listeCommandeProduitStockByReference($cmd->id_user, $cmd->id_produit, $cmd->reference);
                //   $fournisseur_produit=$this->DepotFournisseurModel->getlisteFournisseurByProduitWithUser($cmd->id_user, $cmd->id_produit);
                $liste_commande_produit[$key]->qte_stock_tfi = ($stock_tfi) ? $stock_tfi->qte_stock_tfi : 0;
                $liste_commande_produit[$key]->qte_stock_transit = ($stock_tfi) ? $stock_tfi->qte_stock_transit : 0;
                $liste_commande_produit[$key]->qte_stock_virtuel_clr = ($stock_tfi) ? $stock_tfi->qte_stock_virtuel_clr : 0;
                $liste_commande_produit[$key]->qte_stock_virtuel = ($stock_tfi) ? $stock_tfi->qte_stock_virtuel : 0;
                // $liste_commande_produit[$key]->stock_fournisseur = ($fournisseur_produit) ? $fournisseur_produit : 0;
            }
            if ($qte_colis == null)
                $qte_colis = 0;

            //

            if (($cmd->id_categorie == ID_PRODUIT_COUTEUX || $cmd->id_categorie == ID_PRODUIT_EPISPE) && $cmd->reference != null && !empty($cmd->reference))
                $liste_commande_produit[$key]->quantite = 1;

            //  Quantité manquante à expédier
            $liste_commande_produit[$key]->qte_manquante = $cmd->quantite - $qte_colis;


        }

        // liste des colis de la commmande
        $liste_colis = $this->CommandeTFIModel->getColisByCmd($id_commande);

        // liste des colis rexel de la commmande
        $liste_colis_rexel = $this->CommandeTFIModel->getColisRexelByCmd($id_commande);

        $type_colis = 0;

        if(!empty($liste_colis)) {
            foreach ($liste_colis as $key => $colis) {
                if ($liste_colis[$key]->id_parent)
                    $liste_colis[$key]->liste_colis_produit = $this->CommandeTFIModel->listeMultiColisProduit($colis->id_colis);
                else
                    $liste_colis[$key]->liste_colis_produit = $this->CommandeTFIModel->listeColisProduit($colis->id_colis);
                $liste_colis[$key]->liste_colis_historique = $this->CommandeTFIModel->listeColisHistorique($colis->id_colis);
            }
            $type_colis = 1;

        }

        else {

            foreach ($liste_colis_rexel as $key => $colis) {

                $liste_colis_rexel[$key]->liste_colis_produit = $this->CommandeTFIModel->listeColisRexelProduit($colis->id_colis);
                $liste_colis_rexel[$key]->liste_colis_historique = null;
            }

            $liste_colis = $liste_colis_rexel;

            $type_colis = 2;

        }

        $depotUPS = $this->DepotModel->getIdDepotByUser($commande->id_user);
        $buckup_cdp = false;
        $cdp = false;
        if (!has_permission(ADMIN_PROFILE) && has_permission(CDT_PROFILE) && ($this->BuckupModel->isBuckupofCDP()->count)) {
            $buckup_cdp = true;
            $sup = $sup = $this->UtilisateurModel->getSuperviseurTFI($userId)->superviseur;
            $cdp = $this->UtilisateurModel->getUser($sup);
        }
        if(isset($cmd))
            $fournisseur = $this->DepotFournisseurModel->getlisteFournisseurByUser($cmd->id_user);
        else
            $fournisseur= null;

        foreach ($liste_commande_produit as $key => $p) {
            $last_date = $this->ProduitModel->getLastReceivedDate($p->id_produit, $cmd->id_user);
            if ($last_date) {
                $liste_commande_produit[$key]->last_date = $this->ProduitModel->getLastReceivedDate($p->id_produit, $cmd->id_user)->date_reception;
                $liste_commande_produit[$key]->cmd_reference = $last_date->reference;
            }

            else {
                $liste_commande_produit[$key]->last_date = null;
                $liste_commande_produit[$key]->cmd_reference = null;
            }
        }

        /*var_dump($liste_commande_produit);
         die();*/
        //  $livraison = (object) array('type_livree' => null);

        if ($commande != null) {
            $this->load->view('CommandeTFI/detail', [
                    "commande" => $commande,
                    "fournisseurs" => $fournisseur,
                    "depotUPS" => $depotUPS,
                    "status" => $status,
                    "pack" => $getPack,
                    "liste_colis" => $liste_colis,
                    "liste_commande_produit" => $liste_commande_produit,
                    "type_livrer" => $this->LivrerModel->typeLivree(),
                    //"livraison" => (($this->LivrerModel->findLivreeCmd($id_commande))? ($this->LivrerModel->findLivreeCmd($id_commande)) : $livraison),
                    "livraison" => $this->LivrerModel->findLivreeCmd($id_commande),
                    "user_adresseclr" => $this->UserAdressesModel->getAdresseCLRUser($commande->id_user),
                    "depot_retrait" => $this->DepotFournisseurModel->getlisteDepotRelaisByUser($commande->id_user),
                    "depot_rexel" => $this->DepotFournisseurModel->getDepotRexelByUser($commande->id_user),
                    "buckup_cdp" => $buckup_cdp,
                    "cdp" => $cdp,
                    "rexel_exist" => $rexel_exist,
                ]
            );
        } else
            show_404();

    }

    public function dater()
    {
        var_dump($this->CommandeTFIModel->DateDernierProduit());
    }

    private function filtrerProduits(&$liste_produits, $idtfi)
    {
        $user_tv = $this->TailleVetementModel->getTailleVetementWithUser($idtfi);
        if (empty($user_tv->id_taille_vetement))
            return "Cet utilisateur n'a pas de taille vêtement !!";
        foreach ($liste_produits as $key => $produit) {
            $produit_categorie_taille = $this->ProduitCategorieTailleModel->getCategorieTailleByProduit($produit->id_produit);
            if ($produit_categorie_taille != null) {
                $equal = false;
                switch ($produit_categorie_taille->type_produit) {
                    case 'Pantalon':
                        $equal = ($user_tv->pantalon == $produit_categorie_taille->taille);
                        break;
                    case 'Gants':
                        $equal = ($user_tv->gants == $produit_categorie_taille->taille);
                        break;
                    case 'Chaussures':
                        $equal = ($user_tv->chaussures == $produit_categorie_taille->taille);
                        break;
                    case 'TEE-Shirt':
                        $equal = ($user_tv->tee_shirt == $produit_categorie_taille->taille);
                        break;
                    case 'Polo':
                        $equal = ($user_tv->polo == $produit_categorie_taille->taille);
                        break;
                    case 'Veste':
                        $equal = ($user_tv->veste == $produit_categorie_taille->taille);
                        break;
                    case 'Parka':
                        $equal = ($user_tv->parka == $produit_categorie_taille->taille);
                        break;
                }
                if (!$equal)
                    unset($liste_produits[$key]);
            }
        }
        return "";
    }

    /**
     * Methode qui permet d'afficher la vue pour
     * ajouter une commande TFI
     */
    public function ajouter($idtfi, $logistique = null, $epi_outillage = null, $refait_pl = null)
    {
        $id_user = $idtfi;
        $check_taille_menque = false;
        $this->session->unset_userdata('outillage_file');
        $usertfi = $this->UtilisateurModel->checkUserById($idtfi);
        if ($usertfi->id_role == 6) {
            $superviseur = $this->UtilisateurModel->getSuperviseurTFI($usertfi->id_user);
            $idtfi = $superviseur->superviseur;
        } else
            $idtfi = $this->user->getUser();
        $liste_produits = [];
        $liste_pack_avec_produits = [];
        if ($logistique) {
            if($logistique=="logistique"){
                $liste_produits = $this->ProduitModel->produitLogistique($epi_outillage,$usertfi->id_poste);
            }else{
                $liste_produits = $this->ProduitModel->produitAerien();
            }

            foreach ($liste_produits as $key => $produit) {
                if ($produit->id_categorie == 1 && $produit->type_vetement != "accessoire") {
                    if (!$produit->type_vetement || !$produit->taille) {
                        unset($liste_produits[$key]);
                    } else {
                        $verif_vetement = $this->ProduitModel->verifVetement($id_user, $produit->type_vetement, $produit->taille);
                        if (!$verif_vetement) {
                            unset($liste_produits[$key]);
                        }

                    }


                }

            }
            if ($epi_outillage) {
                $vetement = $this->db->get_where("taille_vetement", ["id_taille_vetement" => $this->UtilisateurModel->getUtilisateur($idtfi)->id_taille_vetement])->row();
                foreach ($vetement as $v) {
                    if (!$v)
                        $check_taille_menque = true;
                }
            }
            $add_in_tfi = false;
        } else {
            if (isset($usertfi->id_poste) && $usertfi->id_poste != null) {
                $liste_produits = $this->CommandeTFIModel->listeProduitsByTFI($idtfi);
                $liste_pack_avec_produits = $this->CommandeTFIModel->listePackProduitsByTFI($idtfi);
                $add_in_tfi = true;
            } else {
                $liste_produits = $this->CommandeTFIModel->listeProduitsByCDT();
                $liste_pack_avec_produits = $this->CommandeTFIModel->listePackProduitsByUser();
                $add_in_tfi = false;

            }
        }

        if ($logistique) {
            $message_apres_filtre_produits = $liste_produits;

        } else {
            $message_apres_filtre_produits = $this->filtrerProduits($liste_produits, $idtfi);
        }
        foreach ($liste_produits as $key => $prod) {
            $produit = $this->CommandeTFIModel->getStockTFIByCommande($id_user, $prod->id_produit);
            if ($produit) {
                $liste_produits[$key]->stock_tfi = $produit->stock_tfi;
                $liste_produits[$key]->stock_transit = $produit->stock_transit;
            } else {
                $liste_produits[$key]->stock_tfi = 0;
                $liste_produits[$key]->stock_transit = 0;
            }

        }

        //TODO
        if (!$logistique) {
            foreach ($liste_pack_avec_produits as $key => $prod) {
                if ($usertfi->id_role == 6 && $prod->id_pack == 20) {
                    unset($liste_pack_avec_produits[$key]);
                    continue;
                }
                $produit = $this->CommandeTFIModel->getStockTFIByCommande($id_user, $prod->id_produit);
                if ($produit) {
                    $liste_pack_avec_produits[$key]->stock_tfi = $produit->stock_tfi;
                    $liste_pack_avec_produits[$key]->stock_transit = $produit->stock_transit;
                } else {
                    $liste_pack_avec_produits[$key]->stock_tfi = 0;
                    $liste_pack_avec_produits[$key]->stock_transit = 0;
                }

            }
        } else {
            $liste_pack_avec_produits = false;
        }
        $depotups = $this->DepotModel->getIdDepotByUser($id_user);
        $depotclr = $this->AdressesCLRModel->getIdAdresseByUser($id_user);
        if(empty($liste_produits)){
            $liste_produits=(object)array();
        }
        $this->load->view('CommandeTFI/ajouter',
            [
                "usertfi" => $usertfi,
                "liste_produits" => $liste_produits,
                "liste_packs" => $liste_pack_avec_produits,
                "message_apres_filtre_produits" => $message_apres_filtre_produits,
                "add_in_tfi" => $add_in_tfi,
                "depotups" => $depotups,
                "depotclr" => $depotclr,
                "logistique" => $logistique,
                "epi_outillage" => $epi_outillage,
                "check_taille" => $check_taille_menque
            ]
        );
    }

    /**
     * Methode qui permet d'ajouter une commande TFI
     */
    public function confirmationAjoutPL($id_tfi = null, $epi_outillage = null)
    {


        $check = false;
        $data = $this->input->post();
        $files = $_FILES;
        if (isset($data["qte"]))
            $productsToAdd = $data["qte"];
        else
            $productsToAdd = [];
        foreach ($productsToAdd as $key => $prod) {
            if ($prod[0] <= 0)
                unset($productsToAdd[$key]);
        }
        $data_cmd = array();
        if (count($productsToAdd) > 0) {
            $data_cmd["reference_pl"] = $this->CommandeTFIModel->getReference($this->user->getUser(),$id_tfi);

            $data_cmd["id_user"] = $id_tfi;
            $data_cmd["cree_par"] = $this->user->getUser();
            $data_cmd["date_creation"] = date('Y-m-d H:i:s');
            $data_cmd["comment"] = $data["dest_comm"];
            if ($epi_outillage){
                $data_cmd["comm_outillage_spe"] = 1;
                $data_cmd["type_commande"] = 2;
            }
            else{
                $data_cmd["comm_outillage_spe"] = 0;
                $data_cmd["type_commande"] = $data["type_commande"];
            }

            $data_cmd["nom_prenom"] = $data["dest_name"];
            $data_cmd["adresse"] = $data["dest_add"];
            $this->CommandeTFIModel->addresseToUser($this->user->getUser(), $data_cmd["adresse"]);
            if (has_permission(TFI_PROFILE))
                $data_cmd["id_state"] = EN_ATTENTE_DE_VALIDATION_CDT_PL;
            if (has_permission(CDT_PROFILE))
                $data_cmd["id_state"] = EN_ATTENTE_DE_VALIDATION_UPR_PL;
            if (has_permission(CDT_PROFILE) && $epi_outillage)
                $data_cmd["date_validation_cdt"] = $data_cmd["date_creation"];
            if (has_permission(CDP_PROFILE))
                $data_cmd["id_state"] = EN_ATTENTE_DE_VALIDATION_SLA_PL;
            if (has_permission(CDP_PROFILE))
                $data_cmd["date_validation_upr"] = $data_cmd["date_creation"];
            $id_cmd = $this->CommandeTFIModel->ajouter_pl($data_cmd);

            if ($epi_outillage) {
                $target_dir = realpath("./uploads/motif_pl/");
                foreach ($_FILES["userFiles"]["tmp_name"] as $key => $value) {
                    if (count($value)) {

                        $vol = false;
                        if ($data["motif_id"][$key][0] == 3) {
                            $vol=true;
                        }
                        foreach ($value as $key2 => $value2) {

                            if ($value2) {
                                $name = $_FILES["userFiles"]["name"][$key][$key2];
                                $array = (explode(".", $name));
                                $ext = end($array);
                                $target_file = $target_dir . "/" . "prop".$this->user->getUser() . "_" . $id_cmd . "_" . $key . "_" . $key2 . "." . $ext;
                                $last_name = $target_dir . "/" . $this->user->getUser() . "_" . $id_cmd . "_" . $key . "_" . $key2 . "." . $ext;
                                move_uploaded_file($value2, $target_file);
                                $this->do_upload_outillage("$target_file","$last_name",$vol);

                                $this->db->insert("justif_spe_outillage", array("id_produit" => $key, "url" => $this->user->getUser() . "_" . $id_cmd . "_" . $key . "_" . $key2 . "." . $ext, "id_cmd_pl" => $id_cmd));
                            }

                        }
                    }
                }

            }
            if (has_permission(TFI_PROFILE) || $id_tfi!=$this->user->getUser()) {
                if ($epi_outillage) {
                    foreach ($productsToAdd as $key => $prod) {
                        if(has_permission(TFI_PROFILE) && $id_tfi==$this->user->getUser()){
                            $this->CommandeTFIModel->ajouterElementCMD_pl(array("id_cmd_pl" => $id_cmd, "id_produit" => $key, "qte_tfi" => $prod[0], "comment_justif" => $data['motif_comment'][$key][0],"id_motif" => $data['motif_id'][$key][0]));
                        }else if(has_permission(CDT_PROFILE) && $id_tfi!=$this->user->getUser()){
                            $this->CommandeTFIModel->ajouterElementCMD_pl(array("id_cmd_pl" => $id_cmd, "id_produit" => $key, "qte_tfi" => $prod[0],"qte_qisonu" => $prod[0], "comment_justif" => $data['motif_comment'][$key][0],"id_motif" => $data['motif_id'][$key][0]));
                        }else if(has_permission(CDP_PROFILE) && $id_tfi!=$this->user->getUser()){
                            $this->CommandeTFIModel->ajouterElementCMD_pl(array("id_cmd_pl" => $id_cmd, "id_produit" => $key, "qte_tfi" => $prod[0],"qte_upr" => $prod[0],"qte_qisonu" => $prod[0], "comment_justif" => $data['motif_comment'][$key][0],"id_motif" => $data['motif_id'][$key][0]));
                        }
                    }

                } else {
                    foreach ($productsToAdd as $key => $prod) {
                        $this->CommandeTFIModel->ajouterElementCMD_pl(array("id_cmd_pl" => $id_cmd, "id_produit" => $key, "qte_tfi" => $prod[0]));
                    }
                }

            } else if (has_permission(CDT_PROFILE) && $id_tfi==$this->user->getUser()) {
                if ($epi_outillage) {
                    foreach ($productsToAdd as $key => $prod) {
                        $this->CommandeTFIModel->ajouterElementCMD_pl(array("id_cmd_pl" => $id_cmd, "id_produit" => $key, "qte_qisonu" => $prod[0], "comment_justif" => $data['motif_comment'][$key][0],"id_motif" => $data['motif_id'][$key][0]));
                    }
                } else {
                    foreach ($productsToAdd as $key => $prod) {
                        $this->CommandeTFIModel->ajouterElementCMD_pl(array("id_cmd_pl" => $id_cmd, "id_produit" => $key, "qte_qisonu" => $prod[0]));
                    }
                }

            } else {
                if ($epi_outillage) {
                    foreach ($productsToAdd as $key => $prod) {
                        $this->CommandeTFIModel->ajouterElementCMD_pl(array("id_cmd_pl" => $id_cmd, "id_produit" => $key, "qte_upr" => $prod[0], "comment_justif" => $data['motif_comment'][$key][0],"id_motif" => $data['motif_id'][$key][0]));
                    }
                } else {
                    foreach ($productsToAdd as $key => $prod) {
                        $this->CommandeTFIModel->ajouterElementCMD_pl(array("id_cmd_pl" => $id_cmd, "id_produit" => $key, "qte_upr" => $prod[0]));
                    }
                }


            }
        }
        if($id_tfi==$this->user->getUser()){
            $this->session->set_flashdata('data', 'Votre commande a bien été enregistrée.');
            if ($epi_outillage)
                redirect(site_url("CommandeTFI/listeCommandePL/" . $this->user->getUser() . "/0/epi_outillage"));
            else
                redirect(site_url("CommandeTFI/listeCommandePL/" . $this->user->getUser()));
        }else
        {
            $this->session->set_flashdata('data', 'Commande a bien été enregistrée.');
            redirect(site_url("Utilisateur/TFIliste"));
        }

    }

    public function confirmationAjout($idtfi)
    {
        $this->db->trans_begin();
        $check = false;
        $data = $this->input->post();
        $tailles = unserialize(TAILLES_POLO_REF);
        if (isset($data["productsToAdd"]))
            $productsToAdd = $data["productsToAdd"];
        else
            $productsToAdd = [];

        foreach ($productsToAdd as $key => $prod) {
            if ($prod["quantite"] <= 0)
                unset($productsToAdd[$key]);
        }
        if (count($productsToAdd) > 0) {
            $data_cmd["reference"] = $this->CommandeTFIModel->getReference($this->user->getUser(), $idtfi);
            $data_cmd["cree_par"] = $this->user->getUser();
            $data_cmd["date_creation"] = date('Y-m-d H:i:s');
            $data_cmd["commentaire"] = $data["commentaire"];
            $data_cmd["id_user"] = $idtfi;
            if (isset($data["id_pack"]))
                $data_cmd["id_pack"] = $data["id_pack"];

            $id_cmd = $this->CommandeTFIModel->ajouter($data_cmd);

            $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité commandée</th></tr>";
            foreach ($productsToAdd as $key => $prod) {
                if ($productsToAdd[$key]["id_motif_outillage"]) {
                    $data_histo = array(
                        "id_user" => $data_cmd["id_user"],
                        "id_cmd" => $id_cmd,
                        "id_motif_outillage" => $productsToAdd[$key]["id_motif_outillage"],
                        "justif_outillage" => $productsToAdd[$key]["comm_outillage"]
                    );
                    $this->db->insert("outillage_historique", $data_histo);
                }
                $productsToAdd[$key]["id_cmd"] = $id_cmd;
                $table .= "<tr><td style='" . style_td_th_produit . "' align=\"left\">" . $this->ProduitModel->getInfoProduit($prod["id_produit"])->designation . " ( <i>" . $this->ProduitModel->getInfoProduit($prod["id_produit"])->reference_free . " </i>)</td><td style='" . style_td_th . "' align=\"center\" >" . $prod['quantite'] . "</td></tr>";
            }
            $table .= "</table><br>";
            $this->CommandeTFIModel->ajouterElementCMD($productsToAdd);
            if (!has_permission(ADMIN_PROFILE) && has_permission(TFI_PROFILE)) {
                if (AUTO_VALIDATION_COMMANDES_CDT == 0) {
                    $id_statcmd = WAIT_VALIDATION_CDT;
                } elseif (AUTO_VALIDATION_COMMANDES_CDT == 1) {
                    $id_statcmd = WAIT_VALIDATION_CDP;
                }
            } elseif (!has_permission(ADMIN_PROFILE) && has_permission(CDT_PROFILE))
                $id_statcmd = WAIT_VALIDATION_CDP;
            elseif (!has_permission(ADMIN_PROFILE) && has_permission(CDP_PROFILE))
                $id_statcmd = WAIT_VALIDATION;
            elseif (!has_permission(ADMIN_PROFILE) && has_permission(ASL_PROFILE)){
                $id_statcmd = WAIT_VALIDATION;
            }

            $data_stat["id_cmd"] = $id_cmd;
            $data_stat["id_statcmd"] = $id_statcmd;
            $data_stat["cree_par"] = $this->user->getUser();
            $data_stat["date_creation"] = date('Y-m-d H:i:s');

            $this->CommandeTFIModel->changeStatus($data_stat);
            $outillage_files = $this->session->userdata("outillage_file");
            var_dump($data);

            $fp = realpath("./uploads/outillage_justif/");
            $date = date("Y_m_d_H_i_s");
            foreach ($outillage_files as $key => $value) {
                $i = 0;
                $vol = false;
                foreach ($productsToAdd as $keyp => $prod) {
                    if ($productsToAdd[$keyp]["id_motif_outillage"] == 3 && $productsToAdd[$keyp]["id_produit"] == $key) {
                        $vol=true;
                    }
                }
                foreach ($value as $key2 => $file) {
                    $name_file = explode(".", $key2);
                    $ext = $name_file[1];
                    $name_file = "prov".$this->user->getUser() . "_" . $id_cmd . "_" . $key . "_" . $i . "." . $ext;
                    $last_name = $this->user->getUser() . "_" . $id_cmd . "_" . $key . "_" . $i . "." . $ext;
                    $f = fopen($fp . "/$name_file", "w");
                    echo fwrite($f, $file);
                    fclose($f);
                    $this->do_upload_outillage("$fp/$name_file","$fp/$last_name",$vol);
                    $data_outillage = array(
                        "id_cmd" => $id_cmd,
                        "id_produit" => $key,
                        "url" => $last_name
                    );
                    $this->CommandeTFIModel->ajoutOutillageJustif($data_outillage);
                    $i++;
                }

            }
            $this->session->set_flashdata('data', 'Votre commande a bien été enregistrée.');
            echo true;
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $this->session->unset_userdata('outillage_file');
            $message = "";
            $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
            $destinataire = $this->UtilisateurModel->getUser($idtfi);
            $message .= "<span style='float:left;'><b> Émetteur : </b>" . $cree_par->prenom . " " . $cree_par->nom . "</span><span style='float: right'><b> Destinataire : </b>" . $destinataire->prenom . " " . $destinataire->nom . "  </span><br><br>";
            $message .= $table;
            $tabCopy = array();

            if(!has_permission(CDP_PROFILE)) {
                if($this->user->getUser() == $data_cmd["id_user"]) {

                    $superviseur = $this->UtilisateurModel->getUser($this->user->getUser());
                }
                else {

                    $superviseur = $this->UtilisateurModel->getUser($destinataire->superviseur);
                }


                if($superviseur->user_vacances == 1) {

                    if($superviseur->id_user_backup_1 != 0 ) {

                        $email = $this->UtilisateurModel->getUser($superviseur->id_user_backup_1)->email;

                        array_push($tabCopy, $email);

                    }

                    if($superviseur->id_user_backup_2 != 0)
                    {
                        $email = $this->UtilisateurModel->getUser($superviseur->id_user_backup_2)->email;
                        array_push($tabCopy, $email);
                    }
                    if($superviseur->id_user_backup_3 != 0)
                    {
                        $email = $this->UtilisateurModel->getUser($superviseur->id_user_backup_3)->email;
                        array_push($tabCopy, $email);
                    }
                }
            }

            $object = "[SLAM] Nouvelle commande : " . $data_cmd["reference"] . " - " . date('d/m/Y');
            $this->SendMail($destinataire, $message, $object, $tabCopy);

        }

    }

    private function getDestinataire($id)
    {
        $info = $this->CommandeTFIModel->getInfoCommande($id);
        $destinataire = $this->UtilisateurModel->getUser($info->id_user);
        return $destinataire;
    }

    private function SendMail($destinataire, $message, $objet, $tabCopy = array())
    {


        if ($destinataire->superviseur != 0 || !empty($destinataire->superviseur)) {

            $superviseur = $this->UtilisateurModel->getUser($destinataire->superviseur);
            //$tabCopy[0]  = array($superviseur->email);

            array_push($tabCopy, $superviseur->email);
        }
        if (SEND_MAIL_ACTIVE) {
            // array_push($tabCopy, MAIL_FIX);
            $this->sendTo($destinataire->email, $message, $objet, $tabCopy);
        }
    }

    private function infoCommande($id_cmd)
    {
        $info = $this->CommandeTFIModel->getInfoCommande($id_cmd);
        $message = "<span style='float: left'><b>Émetteur : </b>" . $info->cree_par . "</span> <span style='float: right'><b> Destinataire : </b>" . $info->destinataire . "</span><br><br>";
        return $message;
    }

    private function sendTo($mail, $message, $objet, $tabCopy)
    {
        $this->load->library('email');
        $this->email->from(SMTP_USER, 'SLAM');
        $this->email->to($mail);
        if (!empty($tabCopy)) {
            $this->email->cc($tabCopy);
        }

        $this->email->subject($objet);
        $this->email->message($message);
        if (SEND_MAIL_ACTIVE) {
            //   $this->email->bcc("alebon@reseau.free.fr");
            $this->email->send();
        }


    }

    public function getqteproduit($id_produit)
    {
        var_dump($this->CommandeTFIModel->CommandeValiderProduitParCDP($id_produit));
    }

    public function demoproduit($id_produit)
    {
        $qte_produits = $this->CommandeTFIModel->qteproduitvaliderSLA($id_produit);
        echo "<pre>";
        var_dump($qte_produits);
        echo "</pre>";
        $qte_produits_liste = $this->CommandeTFIModel->getqteproduitvaliderslaLastfourweekend($id_produit);
        echo "<pre>";
        var_dump($qte_produits_liste);
        echo "</pre>";
        $qte_somme = $this->CommandeTFIModel->getsomme($id_produit);
        echo "<pre>";
        var_dump($qte_somme);
        echo "</pre>";
        $somme = 0;
        $result = 0;
        for ($i = 1; $i < 7; $i++) {
            $qte_par_mois = $this->CommandeTFIModel->getDebutMois($id_produit, $i);
            $somme = $somme + $qte_par_mois->quantite;
            echo pow(($qte_par_mois->quantite - ($qte_somme->quantite / 6)), 2);
            $result = $result + pow(($qte_par_mois->quantite - ($qte_somme->quantite / 6)), 2);
            echo "<pre>";
            var_dump($qte_par_mois);
            echo "</pre>";

            //  echo $i;
        }
        echo $somme . "<br>" . round($result, 2);
        echo "a = " . round($result, 2);
        echo "b = " . round($result, 2) / 6;
        echo "c = " . sqrt(round($result, 2) / 6);

    }


    /**
     * enregistrer commande
     * @param $idtfi
     */
    public function Enregistrer_commande($idtfi, $logistique = null)
    {
        $data = $this->input->post();

        $this->session->unset_userdata('outillage_file');
        if (ups_clr_obligatoire_pour_commande && !has_permission(ASL_PROFILE)) {
            $usertfi = $this->UtilisateurModel->checkUserById($idtfi);
            $depot_ups = $this->DepotModel->getIdDepotByUser($usertfi->id_user);
            $depot_clr = $this->UserAdressesModel->getAdresseCLRUser($usertfi->id_user);
            if (!$depot_clr && !$depot_ups) {
                if ($this->user->getUser() == $idtfi)
                    $this->session->set_flashdata("depot_error", message1);
                else
                    $this->session->set_flashdata("depot_error", message2);
                redirect(site_url('CommandeTFI/ajouter/' . $idtfi));
                die();
            }

        }

        if ($data == null) {
            redirect(site_url('CommandeTFI/ajouter/' . $idtfi));
        }
        $usertfi = $this->UtilisateurModel->checkUserById($idtfi);
        $depot_ups = $this->DepotModel->getIdDepotByUser($usertfi->id_user);
        $depot_clr = $this->UserAdressesModel->getAdresseCLRUser($usertfi->id_user);
        $liste_produits = [];
        if (isset($usertfi->id_post) && $usertfi->id_post != null)
            $liste_produits = $this->CommandeTFIModel->listeProduitsByTFI($idtfi);
        else
            $liste_produits = $this->CommandeTFIModel->listeProduitsByCDT();


        if ($usertfi->designation == 'TFI' && !has_permission(TFI_PROFILE))
            $mes_commandes_cdt = false;
        else if ($idtfi != $this->user->getUser() && has_permission(CDP_PROFILE))
            $mes_commandes_cdt = false;
        else
            $mes_commandes_cdt = true;

        foreach ($liste_produits as $key => $prod) {
            $produit = $this->CommandeTFIModel->getStockTFIByCommande($idtfi, $prod->id_produit);
            if ($produit) {
                $liste_produits[$key]->stock_tfi = $produit->stock_tfi;
                $liste_produits[$key]->stock_transit = $produit->stock_transit;
            } else {
                $liste_produits[$key]->stock_tfi = 0;
                $liste_produits[$key]->stock_transit = 0;
            }
        }

        if (!has_permission(ADMIN_PROFILE) && (has_permission(CDT_PROFILE) || has_permission(TFI_PROFILE))) {

            if (isset($_FILES["userFiles"])) {

                foreach ($_FILES["userFiles"]["tmp_name"] as $key => $file_path) {
                    foreach ($file_path as $key2 => $file) {
                        if ($file) {
                            $_SESSION['outillage_file'][$key][$_FILES["userFiles"]["name"][$key][$key2]] = file_get_contents($file);
                        }
                    }
                }
            }
        }

        $this->load->view('CommandeTFI/detail_commande',
            [
                "usertfi" => $usertfi,
                "liste_produits" => $liste_produits,
                "liste_qte" => $data,
                "depot_ups" => $depot_ups,
                "depot_clr" => $depot_clr,
                "mes_commandes_cdt" => $mes_commandes_cdt,
                "logistique" => $logistique
            ]
        );

    }

    /**
     * Methode qui permet de supprimer la commande passée en argument
     * @param int $id
     */
    public function supprimer($id)
    {
        $this->CommandeTFIModel->supprimer($id);
        redirect(site_url('CommandeTFI/liste'));
    }

    public function extractRetourColis()
    {
        $data['annees_consom'] = $this->db->select('distinct year(date_creation) as annees')->order_by('annees')->get('commande_tfi')->result();

        //SELECT distinct  as 'annees' FROM matsla.commande_tfi order by annees  ;
        $this->load->view("CommandeTFI/extractRouterColis", $data);
    }

    public function extractRetourColisCVS()
    {
        $date = $this->input->post('date');
        $date_year = date('Y', strtotime($date));
        $date_month = date('m', strtotime($date));
        $liste_retour_ups = $this->ColisModel->retour_ups_by_date($date_year, $date_month);
        $fp = realpath("./public/csv_file");

        $file = fopen($fp . '/demo.csv', 'w');

        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($file, array('Tracking UPS', "Ref Commande", "Créée par", "Destinataire", "Poste Destinataire", 'Sup. destinataire', "cdp destinataire", "Date de retour", "Date d'envoi", "Adresse dépot"), ";");

        foreach ($liste_retour_ups as $key => $value) {

            if (empty($value->sup)) {
                $liste_retour_ups[$key]->sup = $value->destination;
            }
            if ($value->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $value->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS) {
                $adresse_depot = $this->DepotModel->getIdDepotByUser($value->id_user);
                $liste_retour_ups[$key]->adresse = $adresse_depot->adresse . ' ' . $adresse_depot->code_postal . ' ' . $adresse_depot->ville;
            } else if ($value->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
                $adresse_clr = $this->AdressesCLRModel->getIdAdresseByUser($value->id_user);
                $liste_retour_ups[$key]->adresse = $adresse_clr->adresse . ' ' . $adresse_clr->code_postal . ' ' . $adresse_clr->ville;
            }
            $array = array($value->tracking_ups, $value->reference, $value->cree_par, $value->destination, $value->type_poste, $value->sup, $value->cdp, date_fr($value->date_colis_retour), date_fr($value->date_expedition), $value->adresse);
            var_dump($array);
            fputcsv($file, $array, ";");
        }

        fclose($file);
        echo json_encode(true);

    }
    public function demoproduits($id_produit='10'){
        echo "<pre>";
        var_dump($this->CommandeTFIModel->GetSUMStockValiderByProduit($id_produit));
        $stockVirtualARG = ($this->CommandeTFIModel->GetSUMStockValiderByProduit($id_produit)) ? $this->CommandeTFIModel->GetSUMStockValiderByProduit($id_produit) : 0;
        $stockVirtualARGSum = ($stockVirtualARG) ? $stockVirtualARG->sum_stock_virtuel : 0;
        $somStock=((2)-$stockVirtualARGSum);
        echo $somStock;
        echo "</pre>";
    }

    private function checkStockArgAndClr($id_cmd, $data)
    {
        var_dump($data);
        $qtt_val = intval($data["som_val"]);
        $qtt_dmd = intval($data["som_cmd"]);
        $mode_preparation_livraison = $data['type_livre'];
        $posr = strpos($data["type_livre"], 'r');
        $posd = strpos($data["type_livre"], 'd');

        if ($posr !== false) {
            $mode_preparation_livraison = 6;
            $addresse_livrasion = explode(" ", $data["type_livre"])[0];
        } else if ($posd !== false) {
            $addresse_livrasion = explode(" ", $data["type_livre"])[0];
            $mode_preparation_livraison = 7;
        }
        if ($qtt_val == $qtt_dmd) {
            $produits_cmd = $this->CommandeTFIModel->listeCommandeProduit($id_cmd);
            $isValidat = true;
            // $mode_preparation_livraison = $data['type_livre'];
            foreach ($produits_cmd as $produit) {

                $stockVirtualARG = ($this->CommandeTFIModel->GetVirtualStockByProduit($produit->id_produit)) ? $this->CommandeTFIModel->GetVirtualStockByProduit($produit->id_produit) : 0;
                $stockVirtualCLR = ($this->CommandeTFIModel->GetVirtualStockClrByProduit($id_cmd, $produit->id_produit)) ? $this->CommandeTFIModel->GetVirtualStockClrByProduit($id_cmd, $produit->id_produit) : 0;
                $stockVirtualARGSum = ($stockVirtualARG) ? $stockVirtualARG->sum_stock_virtuel : 0;
                $stockVirtualCLRSum = ($stockVirtualCLR) ? $stockVirtualCLR->sum_stock_virtuel_clr : 0;
                if ((($mode_preparation_livraison == MODE_LIVRAISON_ARG_ENVOI_UPS ||
                            $mode_preparation_livraison == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) ||
                            $mode_preparation_livraison == MODE_LIVRAISON_ARG_RETRAIT_CLR) &&
                        ($produit->stock_arg - $stockVirtualARGSum + $produit->stock_publi) < $produit->quantite && $produit->quantite)
                    ||
                    (($mode_preparation_livraison == MODE_LIVRAISON_CLR_ENVOI_UPS ||
                            $mode_preparation_livraison == MODE_LIVRAISON_CLR_RSP) &&
                        ($produit->stock_clr - $stockVirtualCLRSum) < $produit->quantite && $produit->quantite)
                ) {
                    $isValidat = false;
                    break;
                } elseif ($mode_preparation_livraison == 6 || $mode_preparation_livraison == 7) {
                    $stockdepotfournisseur = ($this->DepotFournisseurModel->getStockFournisseur($produit->id_produit, $addresse_livrasion)) ? $this->DepotFournisseurModel->getStockFournisseur($produit->id_produit, $addresse_livrasion)->stock_fournisseur : 0;
                    if ($stockdepotfournisseur == 0) {
                        $isValidat = false;
                        break;
                    } else if ($stockdepotfournisseur < $produit->quantite) {
                        $isValidat = false;
                        break;
                    }
                }
            }
            return $isValidat;
        } else {
            $produits_temp = $this->CommandeTFIModel->listeCommandeProduit($id_cmd);
            $produits_cmd = array();
            foreach ($produits_temp as $key => $produit) {
                $produits_cmd[$produit->id_produit] = $produit;
                unset($produits_temp[$key]);
            }
            unset($produits_temp);

            $tab_val = null;
            $tab_cmd = null;

            $tab_val = $data['quantite_validee'];
            $isValidat = true;
            foreach ($tab_val as $key => $ele) {

                $temp = explode("_", $ele);
                $stockVirtualARG = ($this->CommandeTFIModel->GetVirtualStockByProduit($temp[1])) ? $this->CommandeTFIModel->GetVirtualStockByProduit($temp[1]) : 0;
                $stockVirtualCLR = ($this->CommandeTFIModel->GetVirtualStockClrByProduit($id_cmd, $temp[1])) ? $this->CommandeTFIModel->GetVirtualStockClrByProduit($id_cmd, $temp[1]) : 0;
                $stockVirtualARGSum = ($stockVirtualARG) ? $stockVirtualARG->sum_stock_virtuel : 0;
                $stockVirtualCLRSum = ($stockVirtualCLR) ? $stockVirtualCLR->sum_stock_virtuel_clr : 0;
                if ($temp[0] > 0) {
                    //  $mode_preparation_livraison = $data['type_livre'];
                    if ((($mode_preparation_livraison == MODE_LIVRAISON_ARG_ENVOI_UPS ||
                                $mode_preparation_livraison == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) ||
                                $mode_preparation_livraison == MODE_LIVRAISON_ARG_RETRAIT_CLR) &&
                            ($produits_cmd[$temp[1]]->stock_arg - $stockVirtualARGSum + $produits_cmd[$temp[1]]->stock_publi) < intval($temp[0]) && intval($temp[0]))
                        ||
                        (($mode_preparation_livraison == MODE_LIVRAISON_CLR_ENVOI_UPS ||
                                $mode_preparation_livraison == MODE_LIVRAISON_CLR_RSP) &&
                            ($produits_cmd[$temp[1]]->stock_clr - $stockVirtualCLRSum) < intval($temp[0]) && intval($temp[0]))
                    ) {
                        $isValidat = false;
                        break;
                    } elseif ($mode_preparation_livraison == 6 || $mode_preparation_livraison == 7) {
                        $stockdepotfournisseur = ($this->DepotFournisseurModel->getStockFournisseur($temp[1], $addresse_livrasion)) ? $this->DepotFournisseurModel->getStockFournisseur($temp[1], $addresse_livrasion)->stock_fournisseur : 0;

                        //var_dump($temp[1],$stockdepotfournisseur,$addresse_livrasion);
                        if ($stockdepotfournisseur == 0) {
                            $isValidat = false;
                            break;
                        } else if ($stockdepotfournisseur < $produit->quantite) {
                            $isValidat = false;
                            break;
                        }
                    }
                }

            }
            //  die();
            return $isValidat;
        }
    }

    /**
     * Methode qui permet de valider la commande
     */
    public function valider()
    {
        $data = $this->input->post();

        $cmd = $this->CommandeTFIModel->getCommandeById($data["id_cmd"]);
        if($this->DepotModel->getIdDepotByUser($cmd->id_user)->id_depot==DEPOT_ID_DEPOT && DEBUG_ST_OUEN==1 && ($data["type_livre"]==MODE_LIVRAISON_ARG_ENVOI_UPS || $date["type_livre"]==MODE_LIVRAISON_CLR_ENVOI_UPS || $date["type_livre"]==MODE_LIVRAISON_ARG_RETRAIT_CLR )){
            // echo $data["type_livre"]."<br>";
            $data["type_livre"]=MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03;
            // echo $data["type_livre"];
        }


        $posr = strpos($data["type_livre"], 'r');
        $posd = strpos($data["type_livre"], 'd');
        $mode_livraison = "";
        $addresse_livrasion = "";
        $flash_livre = $data["type_livre"];
        $comm_fournisseur = "";
        if ($posr !== false) {
            $mode_livraison = 6;
            $addresse_livrasion = explode(" ", $data["type_livre"])[0];
        } else if ($posd !== false) {
            $addresse_livrasion = explode(" ", $data["type_livre"])[0];
            $mode_livraison = 7;
        }
        //   var_dump($data);
        //  die();
        $id = $data["id_cmd"];
        if ($this->checkStockArgAndClr($id, $data)) {
            $this->db->trans_begin();
            $productsToAdd = [];

            $qtt_val = intval($data["som_val"]);
            $qtt_dmd = intval($data["som_cmd"]);
            $qtt_ref = intval($data["som_ref"]);
            $id_statcmd = VALIDATED;

            $cmd = $this->CommandeTFIModel->getCommandeById($id);
            if (isset($data['commantaire_fournisseur']) && ($mode_livraison == 6 || $mode_livraison == 7)) {
                $this->CommandeTFIModel->updateCommandeForCommantaireFournisseur($id, $data['commantaire_fournisseur']);
                $comm_fournisseur = $data['commantaire_fournisseur'];
                unset($data['commantaire_fournisseur']);
            }
            unset($data['commantaire_fournisseur']);
            $nb_prod_couteux = $this->CommandeTFIModel->getNbProdCouteuxByCmd($id);
            if ($nb_prod_couteux > 0) {
                $id_statcmd = WAIT_VALIDATION_SLAN;
            }

            if ($qtt_dmd == $qtt_val || $qtt_dmd == ($qtt_val + $qtt_ref)) {
                if ($id_statcmd == VALIDATED) {

                    $tab_val = null;
                    $tab_cmd = null;

                    $tab_val = $data['quantite_validee'];
                    $tab_ref = $data['quantite_ref'];
                    foreach ($tab_val as $key => $ele) {
                        $temp = explode("_", $ele);
                        $ref = explode("_", $tab_ref[$key]);
                        if ($ref[0] > 0) {
                            $refus = array(
                                "id_cmd" => $data["id_cmd"],
                                "id_produit" => $ref[1],
                                "id_refuseur" => $this->session->userdata("user_id"),
                                "date_refus" => date("Y-m-d H:i:s"),
                                "quantite" => $ref[0]
                            );
                            $this->CommandeTFIModel->produit_refuser($refus);
                        }
                        $produit = $this->ProduitModel->getProduit($temp[1]);

                        if ($data['type_livre'] == MODE_LIVRAISON_CLR_ENVOI_UPS || $data['type_livre'] == MODE_LIVRAISON_ARG_ENVOI_UPS || $data['type_livre'] == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $data['type_livre'] == MODE_LIVRAISON_ARG_RETRAIT_CLR || $data['type_livre'] == MODE_LIVRAISON_CLR_RSP) {
                            if ($temp[0] > 0 && $produit->id_categorie != ID_PRODUIT_EPISPE) {

                                $this->ProduitModel->gererStockVitruel($temp[0], ['id_cmd' => $id, 'id_user' => $cmd->id_user, 'id_produit' => $temp[1]], '+', $data['type_livre']);
                            } elseif ($temp[0] > 0 && $produit->id_categorie == ID_PRODUIT_EPISPE) {
                                for ($i = 1; $i <= $temp[0]; $i++)
                                    $this->ProduitModel->gererStockVitruelProduitCouteux(1, ['id_cmd' => $id, 'id_user' => $cmd->id_user, 'id_produit' => $temp[1], 'reference' => $this->randStrGen(32)], '+', $data['type_livre']);
                            }
                        }

                        if ($mode_livraison == 6 || $mode_livraison == 7) {
                            if ($temp[0] > 0 && $produit->id_categorie != ID_PRODUIT_EPISPE) {
                                $this->ProduitModel->gererStockVitruel($temp[0], ['id_cmd' => $id, 'id_user' => $cmd->id_user, 'id_produit' => $temp[1]], '+', $mode_livraison);
                            } elseif ($temp[0] > 0 && $produit->id_categorie == ID_PRODUIT_EPISPE) {
                                for ($i = 1; $i <= $temp[0]; $i++)
                                    $this->ProduitModel->gererStockVitruelProduitCouteux(1, ['id_cmd' => $id, 'id_user' => $cmd->id_user, 'id_produit' => $temp[1], 'reference' => $this->randStrGen(32)], '+', $mode_livraison);
                            }
                            // echo $mode_livraison;
                            //  die();
                        }

                        if ($qtt_dmd == ($qtt_val + $qtt_ref)) {
                            $ref = explode("_", $tab_ref[$key]);
                            $this->CommandeTFIModel->modifierElementCMD($id, $temp[1], ['quantite_demandee' => ($ref[0] + $temp[0]), 'quantite_validee' => $temp[0]]);

                        }
                    }
                    if ($data['type_livre'] == MODE_LIVRAISON_CLR_ENVOI_UPS || $data['type_livre'] == MODE_LIVRAISON_ARG_ENVOI_UPS || $data['type_livre'] == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03)) {
                        $addresse = $this->DepotModel->getIdDepotByUser($cmd->id_user)->id_depot;
                        $data_livre = array('id_cmd' => $id, 'type_livree' => $data['type_livre'], 'id_depot_ups' => $addresse, 'id_depot_clr' => NULL);
                    } else if ($data['type_livre'] == MODE_LIVRAISON_ARG_RETRAIT_CLR || $data['type_livre'] == MODE_LIVRAISON_CLR_RSP) {
                        $addresse = $this->AdressesCLRModel->getIdAdresseByUser($cmd->id_user)->id_adresses_clr;
                        $data_livre = array('id_cmd' => $id, 'type_livree' => $data['type_livre'], 'id_depot_ups' => NULL, 'id_depot_clr' => $addresse);
                    } else if ($mode_livraison == 6 || $mode_livraison == 7) {
                        $data_livre = array('id_cmd' => $id, 'type_livree' => $mode_livraison, 'id_depot_ups' => NULL, 'id_depot_clr' => NULL, 'id_depot_fournisseur' => $addresse_livrasion);
                    }

                    $livrer = $this->LivrerModel->findLivreeCmd($id);
                    if (empty($livrer)) {
                        $add = $this->LivrerModel->ajouterLivrer($data_livre);
                    } else {
                        $edit = $this->LivrerModel->modifierLivrer($id, $data_livre);
                    }

                }
                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();
                    $message = "";
                    $message .= $this->infoCommande($id);
                    $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
                    $message .= "<b> Validée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . " <br><br>";
                    if (!empty($data["commentaire"])) {
                        $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
                    }
                    $tab_val = null;
                    $tab_cmd = null;
                    if ($id_statcmd == VALIDATED) {
                        $tab_cmd = $data['quantite_commandee'];
                        $tab_val = $data['quantite_validee'];
                        $tab_ref = $data['quantite_ref'];
                        $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité commandée</th><th style='" . style_td_th . "' align=\"center\">Quantité validée</th><th style='" . style_td_th . "' align=\"center\">Quantité refusée</th></tr>";
                        foreach ($tab_val as $key => $ele) {
                            $temp = explode("_", $ele);
                            $ref = explode("_", $tab_ref[$key]);
                            $produit = $this->ProduitModel->getProduit($temp[1]);
                            if ($temp[0] > 0 && $produit->id_categorie != ID_PRODUIT_EPISPE) {
                                $table .= "<tr><td style='" . style_td_th_produit . "' align=\"left\">" . $this->ProduitModel->getInfoProduit($temp[1])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . ($temp[0] + $ref[0]) . "</td><td style='" . style_td_th . "' align=\"center\">" . $temp[0] . "</td><td style='" . style_td_th . "' align=\"center\">" . (($ref[0]) ? $ref[0] : '0') . "</td></tr>";
                            } elseif ($temp[0] > 0 && $produit->id_categorie == ID_PRODUIT_EPISPE) {
                                $table .= "<tr><td style='" . style_td_th_produit . "' align=\"left\">" . $this->ProduitModel->getInfoProduit($temp[1])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . ($temp[0] + $ref[0]) . " </td><td style='" . style_td_th . "' align=\"center\">" . $temp[0] . "</td><td style='" . style_td_th . "' align=\"center\">" . (($ref[0]) ? $ref[0] : '0') . "</td></tr>";
                            }
                        }
                        $table .= "</table><br>";
                        if (count($tab_val) > 0) {
                            $message .= $table;
                        }
                        $destinataire = $this->getDestinataire($id);
                        $reference = $this->CommandeTFIModel->getInfoCommande($id)->reference;
                        $object = "[SLAM] Validation de commande : " . $reference . " - " . date('d/m/Y');
                        $tabCopy = array();
                        $this->SendMail($destinataire, $message, $object, $tabCopy);

                    } else if ($id_statcmd == WAIT_VALIDATION_SLAN) {
                        $tab_cmd = $data['quantite_commandee'];
                        $tab_val = $data['quantite_validee'];
                        $tab_ref = $data['quantite_ref'];
                        $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité commandée</th><th style='" . style_td_th . "' align=\"center\">Quantité validée</th><th style='" . style_td_th . "' align=\"center\">Quantité refusée</th></tr>";
                        foreach ($tab_val as $key => $ele) {
                            $temp = explode("_", $ele);
                            $ref = explode("_", $tab_ref[$key]);
                            if ($ref[0] > 0) {
                                $refus = array(
                                    "id_cmd" => $data["id_cmd"],
                                    "id_produit" => $ref[1],
                                    "id_refuseur" => $this->session->userdata("user_id"),
                                    "date_refus" => date("Y-m-d H:i:s"),
                                    "quantite" => $ref[0]
                                );
                                $this->CommandeTFIModel->produit_refuser($refus);
                            }
                            $produit = $this->ProduitModel->getProduit($temp[1]);
                            if ($temp[0] > 0 && $produit->id_categorie != ID_PRODUIT_EPISPE) {
                                $table .= "<tr><td style='" . style_td_th_produit . "' align=\"left\">" . $this->ProduitModel->getInfoProduit($temp[1])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . ($temp[0] + $ref[0]) . "</td><td style='" . style_td_th . "' align=\"center\">" . $temp[0] . " </td><td style='" . style_td_th . "' align=\"center\">" . (($ref[0]) ? $ref[0] : '0') . " </td></tr>";
                            } elseif ($temp[0] > 0 && $produit->id_categorie == ID_PRODUIT_EPISPE) {
                                $table .= "<tr><td style='" . style_td_th_produit . "' align=\"left\">" . $this->ProduitModel->getInfoProduit($temp[1])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . ($temp[0] + $ref[0]) . "</td><td style='" . style_td_th . "' align=\"center\">" . $temp[0] . " </td><td style='" . style_td_th . "' align=\"center\">" . (($ref[0]) ? $ref[0] : '0') . "</td></tr>";
                            }
                            if ($qtt_dmd == ($qtt_val + $qtt_ref)) {
                                $ref = explode("_", $tab_ref[$key]);
                                $this->CommandeTFIModel->modifierElementCMD($id, $temp[1], ['quantite_demandee' => ($ref[0] + $temp[0]), 'quantite_validee' => $temp[0]]);
                            }
                        }
                        $table .= "</table><br>";
                        if (count($tab_val) > 0) {
                            $message .= $table;
                        }
                        $destinataire = $this->getDestinataire($id);
                        $reference = $this->CommandeTFIModel->getInfoCommande($id)->reference;
                        $object = "[SLAM] Validation de commande : " . $reference . " - " . date('d/m/Y');
                        $tabCopy = array();

                        $this->SendMail($destinataire, $message, $object, $tabCopy);
                    }
                }
            } else {

                $inputs = array();
                $inputs['reference'] = $this->CommandeTFIModel->getReference($this->user->getUser(), $cmd->id_user);
                $inputs['numero'] = $cmd->numero;
                $inputs['cree_par'] = $cmd->cree_par;
                $inputs['date_creation'] = $cmd->date_creation;
                $inputs['id_user'] = $cmd->id_user;
                if ($cmd->id_parent_reliquat) {
                    $inputs['id_parent_reliquat'] = $cmd->id_parent_reliquat;
                } else {
                    $inputs['id_parent_reliquat'] = $cmd->id_cmd;
                }

                $id_cmd = $this->CommandeTFIModel->ajouter($inputs);

                $data_stat["id_cmd"] = $id_cmd;
                $data_stat["id_statcmd"] = RELIQUAT_SLA;
                $data_stat["cree_par"] = $this->user->getUser();
                $data_stat["date_creation"] = date('Y-m-d H:i:s');

                $this->CommandeTFIModel->changeStatus($data_stat);

                $tab_val = null;
                $tab_cmd = null;
                $liste_produit_mail = " ";
                $tab_val = $data['quantite_validee'];

                $tab_cmd = $data['quantite_commandee'];
                $tab_ref = $data['quantite_ref'];
                foreach ($tab_val as $key => $ele) {
                    $temp = explode("_", $ele);
                    $ref = explode("_", $tab_ref[$key]);
                    if ($ref[0] > 0) {
                        $refus = array(
                            "id_cmd" => $data["id_cmd"],
                            "id_produit" => $ref[1],
                            "id_refuseur" => $this->session->userdata("user_id"),
                            "date_refus" => date("Y-m-d H:i:s"),
                            "quantite" => $ref[0]
                        );
                        $this->CommandeTFIModel->produit_refuser($refus);
                    }
                    $produit = $this->ProduitModel->getProduit($temp[1]);
                    $ref = explode("_", $tab_ref[$key]);
                    if (($tab_cmd[$key] - $temp[0] - $ref[0]) > 0) {
                        $this->CommandeTFIModel->ajouterElementCMD(array('0' => ['id_cmd' => $id_cmd, 'id_produit' => $temp[1], 'quantite' => ($tab_cmd[$key] - $temp[0] - $ref[0])]));
                        $liste_produit_mail .= $this->ProduitModel->getInfoProduit($temp[1])->designation . " (" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . ") => quantités commandées : " . $tab_cmd[$key] . " , quantités validées : " . $tab_val[$key] . " , quantités refusées " . $ref[0] . "<br>";
                    } else {
                        $liste_produit_mail .= $this->ProduitModel->getInfoProduit($temp[1])->designation . " (" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . ") => quantités commandées : " . $tab_cmd[$key] . " , quantités validées : " . $tab_val[$key] . "  , quantités refusées " . $ref[0] . "<br>";
                    }
                    $this->CommandeTFIModel->modifierElementCMD($id, $temp[1], ['quantite_demandee' => $tab_cmd[$key], 'quantite_validee' => $temp[0]]);
                    if ($data['type_livre'] == MODE_LIVRAISON_CLR_ENVOI_UPS || $data['type_livre'] == MODE_LIVRAISON_ARG_ENVOI_UPS || $data['type_livre'] == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $data['type_livre'] == MODE_LIVRAISON_ARG_RETRAIT_CLR || $data['type_livre'] == MODE_LIVRAISON_CLR_RSP) {

                        if ($temp[0] > 0 && $produit->id_categorie != ID_PRODUIT_EPISPE && $id_statcmd == VALIDATED) {
                            $this->ProduitModel->gererStockVitruel($temp[0], ['id_cmd' => $id, 'id_user' => $cmd->id_user, 'id_produit' => $temp[1]], '+', $data['type_livre']);
                        } elseif ($temp[0] > 0 && $produit->id_categorie == ID_PRODUIT_EPISPE && $id_statcmd == VALIDATED) {
                            for ($i = 1; $i <= $temp[0]; $i++)
                                $this->ProduitModel->gererStockVitruelProduitCouteux(1, ['id_cmd' => $id, 'id_user' => $cmd->id_user, 'id_produit' => $temp[1], 'reference' => $this->randStrGen(32)], '+', $data['type_livre']);
                        }
                    } elseif ($mode_livraison == 6 || $mode_livraison == 7) {
                        if ($temp[0] > 0 && $produit->id_categorie != ID_PRODUIT_EPISPE && $id_statcmd == VALIDATED) {
                            $this->ProduitModel->gererStockVitruel($temp[0], ['id_cmd' => $id, 'id_user' => $cmd->id_user, 'id_produit' => $temp[1]], '+', $mode_livraison);
                        } elseif ($temp[0] > 0 && $produit->id_categorie == ID_PRODUIT_EPISPE && $id_statcmd == VALIDATED) {
                            for ($i = 1; $i <= $temp[0]; $i++)
                                $this->ProduitModel->gererStockVitruelProduitCouteux(1, ['id_cmd' => $id, 'id_user' => $cmd->id_user, 'id_produit' => $temp[1], 'reference' => $this->randStrGen(32)], '+', $mode_livraison);
                        }
                    }
                }
                if ($data['type_livre'] == MODE_LIVRAISON_CLR_ENVOI_UPS || $data['type_livre'] == MODE_LIVRAISON_ARG_ENVOI_UPS || $data['type_livre'] == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03)) {
                    $addresse = $this->DepotModel->getIdDepotByUser($cmd->id_user)->id_depot;
                    $data_livre = array('id_cmd' => $id, 'type_livree' => $data['type_livre'], 'id_depot_ups' => $addresse, 'id_depot_clr' => NULL);
                } else if ($data['type_livre'] == MODE_LIVRAISON_ARG_RETRAIT_CLR || $data['type_livre'] == MODE_LIVRAISON_CLR_RSP) {
                    $addresse = $this->AdressesCLRModel->getIdAdresseByUser($cmd->id_user)->id_adresses_clr;
                    $data_livre = array('id_cmd' => $id, 'type_livree' => $data['type_livre'], 'id_depot_ups' => NULL, 'id_depot_clr' => $addresse);
                } else if ($mode_livraison == 6 || $mode_livraison == 7) {
                    $data_livre = array('id_cmd' => $id, 'type_livree' => $mode_livraison, 'id_depot_ups' => NULL, 'id_depot_clr' => NULL, 'id_depot_fournisseur' => $addresse_livrasion);
                }
                $livrer = $this->LivrerModel->findLivreeCmd($id);
                if (empty($livrer)) {
                    $add = $this->LivrerModel->ajouterLivrer($data_livre);
                } else {
                    $edit = $this->LivrerModel->modifierLivrer($id, $data_livre);
                }
                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();

                    $tab_val = null;
                    $tab_cmd = null;
                    $tab_val = $data['quantite_validee'];
                    $tab_cmd = $data['quantite_commandee'];
                    $tab_ref = $data['quantite_ref'];
                    $message = "";
                    $message .= $this->infoCommande($id);
                    $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
                    $message .= "<b> Validée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . " <br><br>";
                    if (!empty($data["commentaire"])) {
                        $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
                    }

                    $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité commandée</th><th style='" . style_td_th . "' align=\"center\">Quantité validée</th><th style='" . style_td_th . "' align=\"center\">Quantités refusée</th><th style='" . style_td_th . "' align=\"center\">Quantité reliquat</th></tr>";
                    foreach ($tab_val as $key => $ele) {
                        $temp = explode("_", $ele);
                        $ref = explode("_", $tab_ref[$key]);
                        $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $this->ProduitModel->getInfoProduit($temp[1])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">$tab_cmd[$key]</td><td align=\"center\" style='" . style_td_th . "'>$temp[0]</td><td align=\"center\" style='" . style_td_th . "'>$ref[0]</td><td style='" . style_td_th . "' align=\"center\">" . ($tab_cmd[$key] - ($temp[0] + $ref[0])) . "</td></tr>";
                    }
                    $table .= "</table></br>";
                    if (count($tab_val) > 0) {
                        $message .= $table;
                    }
                    $destinataire = $this->getDestinataire($id);
                    $reference = $this->CommandeTFIModel->getInfoCommande($id)->reference;
                    $object = "[SLAM] Validation de commande : " . $reference . " - " . date('d/m/Y');
                    $tabCopy = array();

                    $this->SendMail($destinataire, $message, $object, $tabCopy);
                }
            }
            if (($mode_livraison == 6 || $mode_livraison == 7) && $id_statcmd != WAIT_VALIDATION_SLAN) {
                $mode_livraison_name = (($mode_livraison == 6) ? "POINT_RELAI" : "LIV_STANDARD");
                $fournisseur = $this->FournisseurModel->getFournisseurByCMD($cmd->id_cmd);
                $adresse = $this->DepotFournisseurModel->getFournisseurByCMD($cmd->id_cmd);
                $path = FCPATH . "public/csv_file/";
                $date = date("Ymd");
                $file = fopen($path . "F5_ODL_" . $date . "_TLC-" . $cmd->id_cmd . ".csv", 'w');
                $destinataire = $this->UtilisateurModel->getUtilisateur($cmd->id_user);
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($file, array('REF_ODL', 'SOCIETE', 'CODE_ENTITE', 'MODE_LIVRAISON', 'MODE_PAIEMENT', 'LIBELLE_LIVRAISON', 'ADRESSE_LIVRAISON', 'COMPLEMENT_ADRESSE', 'CP_LIVRAISON', 'VILLE_LIVRAISON', 'CODE_POINT_RELAI', 'EMAIL_DESTINATAIRE', 'CONTACT_DESTINATAIRE', 'TEL_CONTACT_DESTINATAIRE', 'TEL_SMS_SUIVI_COMMANDE', 'COMMENTAIRES', 'REF_ARTICLE', 'LIB_CONDT', 'QTE_CONDT', 'QTE_COMMANDE'), ";");
                foreach ($data["quantite_validee"] as $p) {
                    $array = [];
                    if ((int)explode("_", $p)[0] > 0) {
                        $produit_fournisseur = $this->FournisseurModel->getProdFournisseur($fournisseur->id_fournisseur, explode("_", $p)[1]);
                        if (strlen((String)$produit_fournisseur->ref_fournisseur) == 2)
                            $ref_f = "00" . $produit_fournisseur->ref_fournisseur;
                        else if (strlen((String)$produit_fournisseur->ref_fournisseur) == 3)
                            $ref_f = "0" . $produit_fournisseur->ref_fournisseur;
                        else
                            $ref_f = $produit_fournisseur->ref_fournisseur;
                        $array = array($fournisseur->code_fournisseur_livraison . "-" . $cmd->id_cmd, "FREE RESEAU", "411FREERESEAU", $mode_livraison_name, "VIREMENT", "FREE 95 - ARGENTEUIL", $adresse->adresse, " ", $adresse->code_postal, $adresse->ville, $adresse->code_depot_fournisseur, $destinataire->email, $destinataire->nom . " " . $destinataire->prenom, $destinataire->tel, " ", $comm_fournisseur, $ref_f, (($produit_fournisseur->lib_condt_fournisseur) ? utf8_decode($produit_fournisseur->lib_condt_fournisseur) : ""), $produit_fournisseur->qte_condt, explode("_", $p)[0]);
                        fputcsv($file, $array, ";");
                    }

                }


                fclose($file);
            }
            unset($data['quantite_validee']);
            unset($data['quantite_commandee']);
            unset($data['som_val']);
            unset($data['som_cmd']);
            unset($data['type_livre']);
            unset($data['id_user']);
            unset($data['quantite_ref']);
            unset($data['som_ref']);
            $data["id_statcmd"] = $id_statcmd;
            $data["cree_par"] = $this->user->getUser();
            $data["date_creation"] = date('Y-m-d H:i:s');
            $this->CommandeTFIModel->changeStatus($data);

            redirect(site_url(['CommandeTFI/detail', $id]));
        } else {
            if ($flash_livre == MODE_LIVRAISON_CLR_ENVOI_UPS || $flash_livre == MODE_LIVRAISON_ARG_ENVOI_UPS || $flash_livre == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $flash_livre == MODE_LIVRAISON_ARG_RETRAIT_CLR || $flash_livre == MODE_LIVRAISON_CLR_RSP) {
                $this->session->set_flashdata('data_item', array('message' => 'Une erreur est survenu lors de la validation ça peut être un stock (ARG/CLR) manquant', 'class' => 'error'));
            } else {
                $this->session->set_flashdata('data_item', array('message' => 'Une erreur est survenu lors de la validation ça peut être un stock Fournisseur manquant', 'class' => 'error'));
            }
            redirect(site_url(['CommandeTFI/detail', $id]));
        }

    }

    /**
     * Methode qui permet de validerCdp la commande
     *
     */
    public function validerCdp()
    {
        $productsToAdd = [];
        $data = $this->input->post();
        $id = $data["id_cmd"];
        $equipe = $this->input->post("equipe");
        $qtt_val = intval($data["som_val"]);
        $qtt_dmd = intval($data["som_cmd"]);
        $this->db->trans_begin();
        $id_statcmd = WAIT_VALIDATION;

        $data_stat["id_cmd"] = $id;
        $data_stat["id_statcmd"] = $id_statcmd;
        $data_stat["cree_par"] = $this->user->getUser();
        if (isset($data['commentaire']))
            $data_stat["commentaire"] = $data['commentaire'];
        $data_stat["date_creation"] = date('Y-m-d H:i:s');

        $this->CommandeTFIModel->changeStatus($data_stat);

        if ($qtt_dmd != $qtt_val) {

            $tab_val = null;
            $tab_cmd = null;

            $tab_val = $data['quantite_validee'];

            $tab_cmd = $data['quantite_commandee'];
            $table = "<table border=\"1\" width='100%' style='" . style_table . "'><tr style='" . tr . "'><th align=\"left\" style='" . style_td_th_produit . "'>Produit</th><th align=\"center\" style='" . style_td_th . "'>Quantité commandée</th><th align=\"center\" style='" . style_td_th . "'>Quantité validée</th><th align=\"center\" style='" . style_td_th . "'>Quantité refusée</th></tr>";
            foreach ($tab_val as $key => $ele) {
                $temp = explode("_", $ele);

                if (($tab_cmd[$key] - $temp[0]) > 0) {
                    $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $this->ProduitModel->getInfoProduit($temp[1])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td align=\"center\" style='" . style_td_th . "'>$tab_cmd[$key]</td><td style='" . style_td_th . "' align=\"center\">$temp[0]</td><td style='" . style_td_th . "' align=\"center\">" . ($tab_cmd[$key] - $temp[0]) . "</td></tr>";
                    $this->CommandeProduitRefuseModel->ajouter(array('id_cmd' => $id, 'id_refuseur' => $this->user->getUser(), 'id_produit' => $temp[1], 'date_refus' => date('Y-m-d H:i:s'), 'quantite' => ($tab_cmd[$key] - $temp[0])));
                    $this->CommandeTFIModel->modifierProduitCMD($id, $temp[1], $temp[0]);
                } else {
                    $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $this->ProduitModel->getInfoProduit($temp[1])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td align=\"center\" style='" . style_td_th . "'>$temp[0]</td><td align=\"center\" style='" . style_td_th . "'>$temp[0]</td><td style='" . style_td_th . "' align=\"center\">0</td></tr>";
                }

            }
            $table .= "</table><br>";
        } else {
            $tab_val = null;
            $tab_cmd = null;

            $tab_val = $data['quantite_validee'];

            $tab_cmd = $data['quantite_commandee'];
            $table = "<table border=\"1\" width='100%' style='" . style_table . "'><tr style='" . tr . "'><th align=\"left\" style='" . style_td_th_produit . "'>Produit</th><th align=\"center\" style='" . style_td_th . "'>Quantité commandée</th><th align=\"center\" style='" . style_td_th . "'>Quantité validée</th><th align=\"center\" style='" . style_td_th . "'>Quantité refusée</th></tr>";
            foreach ($tab_val as $key => $ele) {
                $temp = explode("_", $ele);

                if (($tab_cmd[$key] - $temp[0]) > 0) {
                    $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $this->ProduitModel->getInfoProduit($temp[1])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td align=\"center\" style='" . style_td_th . "'>$tab_cmd[$key]</td><td style='" . style_td_th . "' align=\"center\">$temp[0]</td><td style='" . style_td_th . "' align=\"center\">" . ($tab_cmd[$key] - $temp[0]) . "</td></tr>";
                } else {
                    $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $this->ProduitModel->getInfoProduit($temp[1])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td align=\"center\" style='" . style_td_th . "'>$temp[0]</td><td align=\"center\" style='" . style_td_th . "'>$temp[0]</td><td style='" . style_td_th . "' align=\"center\">0</td></tr>";
                }

            }
            $table .= "</table><br>";
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $message = "";
            $message .= $this->infoCommande($id);
            $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
            $message .= "<b> Validée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . "  <br><br>";
            if (!empty($data["commentaire"])) {
                $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
            }
            if (count($tab_val) > 0) {
                $message .= $table;
            }
            $destinataire = $this->getDestinataire($id);
            $reference = $this->CommandeTFIModel->getInfoCommande($id)->reference;
            $object = "[SLAM] Validation de commande : " . $reference . " - " . date('d/m/Y');
            $tabCopy = array();
            $this->SendMail($destinataire, $message, $object, $tabCopy);
        }
        if (has_permission(CDT_PROFILE)) {
            redirect(site_url(['CommandeTFI/liste', BUCKUP_CDP]));
        } else {
            $getNextIDCMD = $this->CommandeTFIModel->getNextIdCmd($this->user->getUser(), $id, 'MIN');
            $getpreviousIDCMD = $this->CommandeTFIModel->getNextIdCmd($this->user->getUser(), $id, 'MAX');
            if ($getNextIDCMD) {
                if ($equipe)
                    redirect(site_url(['CommandeTFI/liste', WAIT_VALIDATION_BY_EQUIPE_CDP]));
                else {
                    $id_suivant = $getNextIDCMD->id;
                    redirect(site_url(['CommandeTFI/detail', $id_suivant]));
                }
            } elseif ($getpreviousIDCMD) {
                if ($equipe)
                    redirect(site_url(['CommandeTFI/liste', WAIT_VALIDATION_BY_EQUIPE_CDP]));
                else {
                    $id_suivant = $getpreviousIDCMD->id;
                    redirect(site_url(['CommandeTFI/detail', $id_suivant]));
                }
            } else {
                if ($equipe)
                    redirect(site_url(['CommandeTFI/liste', WAIT_VALIDATION_BY_EQUIPE_CDP]));
                else
                    redirect(site_url(['CommandeTFI/liste', WAIT_VALIDATION_CDP]));
            }

        }

    }

    /**
     * Methode qui permet de validerCdt la commande
     *
     */
    public function validerCdt()
    {
        $productsToAdd = [];
        $data = $this->input->post();

        $id = $data["id_cmd"];

        $this->db->trans_begin();
        $qtt_val = intval($data["som_val"]);
        $qtt_dmd = intval($data["som_cmd"]);

        $data_stat["id_cmd"] = $id;
        $data_stat["id_statcmd"] = WAIT_VALIDATION_CDP;
        $data_stat["cree_par"] = $this->user->getUser();
        if (isset($data['commentaire']))
            $data_stat["commentaire"] = $data['commentaire'];
        $data_stat["date_creation"] = date('Y-m-d H:i:s');

        $this->CommandeTFIModel->changeStatus($data_stat);

        if ($qtt_dmd != $qtt_val) {
            $tab_val = null;
            $tab_cmd = null;

            $tab_val = $data['quantite_validee'];

            $tab_cmd = $data['quantite_commandee'];
            $table = "<table width='100%' style='" . style_table . "'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th  style='" . style_td_th . "' align=\"center\">Quantité commandée</th><th align=\"center\" style='" . style_td_th . "'>Quantité validée</th><th align=\"center\" style='" . style_td_th . "'>Quantités refusée</th></tr>";
            foreach ($tab_val as $key => $ele) {
                $temp = explode("_", $ele);

                if (($tab_cmd[$key] - $temp[0]) > 0) {
                    $this->CommandeProduitRefuseModel->ajouter(array('id_cmd' => $id, 'id_refuseur' => $this->user->getUser(), 'id_produit' => $temp[1], 'date_refus' => date('Y-m-d H:i:s'), 'quantite' => ($tab_cmd[$key] - $temp[0])));
                    $this->CommandeTFIModel->modifierProduitCMD($id, $temp[1], $temp[0]);
                    $table .= "<tr><td  style='" . style_td_th_produit . "' align=\"left\">" . $this->ProduitModel->getInfoProduit($temp[1])->designation . "(<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td  style='" . style_td_th . "' align=\"center\">" . $tab_cmd[$key] . "</td><td  style='" . style_td_th . "' align=\"center\">$temp[0]</td><td  style='" . style_td_th . "' align=\"center\">" . ($tab_cmd[$key] - $temp[0]) . "</td></tr>";
                } else {
                    $table .= "<tr><td  style='" . style_td_th_produit . "' align=\"left\">" . $this->ProduitModel->getInfoProduit($temp[1])->designation . "(<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td  style='" . style_td_th . "' align=\"center\">" . $temp[0] . "</td><td  style='" . style_td_th . "' align=\"center\">$temp[0]</td><td  style='" . style_td_th . "' align=\"center\"> 0 </td></tr>";
                }
            }
            $table .= "</table><br>";
        } else {
            $tab_val = null;
            $tab_cmd = null;

            $tab_val = $data['quantite_validee'];

            $tab_cmd = $data['quantite_commandee'];
            $table = "<table width='100%' style='" . style_table . "'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th  style='" . style_td_th . "' align=\"center\">Quantité commandée</th><th align=\"center\" style='" . style_td_th . "'>Quantité validée</th><th align=\"center\" style='" . style_td_th . "'>Quantités refusée</th></tr>";
            foreach ($tab_val as $key => $ele) {
                $temp = explode("_", $ele);

                if (($tab_cmd[$key] - $temp[0]) > 0) {
                    $table .= "<tr><td  style='" . style_td_th_produit . "' align=\"left\">" . $this->ProduitModel->getInfoProduit($temp[1])->designation . "(<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td  style='" . style_td_th . "' align=\"center\">" . $tab_cmd[$key] . "</td><td  style='" . style_td_th . "' align=\"center\">$temp[0]</td><td  style='" . style_td_th . "' align=\"center\">" . ($tab_cmd[$key] - $temp[0]) . "</td></tr>";
                } else {
                    $table .= "<tr><td  style='" . style_td_th_produit . "' align=\"left\">" . $this->ProduitModel->getInfoProduit($temp[1])->designation . "(<i>" . $this->ProduitModel->getInfoProduit($temp[1])->reference_free . "</i>)" . "</td><td  style='" . style_td_th . "' align=\"center\">" . $temp[0] . "</td><td  style='" . style_td_th . "' align=\"center\">$temp[0]</td><td  style='" . style_td_th . "' align=\"center\"> 0 </td></tr>";
                }
            }
            $table .= "</table><br>";
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $message = "";
            $message .= $this->infoCommande($id);
            $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
            $message .= "<b> Validée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . "  <br><br>";
            if (isset($data["commentaire"])) {
                $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
            }

            if (count($tab_val) > 0) {
                $message .= $table;
            }
            $destinataire = $this->getDestinataire($id);
            $reference = $this->CommandeTFIModel->getInfoCommande($id)->reference;
            $object = "[SLAM] Validation de commande : " . $reference . " - " . date('d/m/Y');
            $tabCopy = array();

            $superviseur = $this->UtilisateurModel->getUser($destinataire->superviseur);
            $superviseur = $this->UtilisateurModel->getUser($superviseur->superviseur);

            if($superviseur->user_vacances == 1) {

                if($superviseur->id_user_backup_1 != 0 ) {

                    $email = $this->UtilisateurModel->getUser($superviseur->id_user_backup_1)->email;
                    array_push($tabCopy, $email);
                }

                if($superviseur->id_user_backup_2 != 0)
                {
                    $email = $this->UtilisateurModel->getUser($superviseur->id_user_backup_2)->email;
                    array_push($tabCopy, $email);
                }
                if($superviseur->id_user_backup_3 != 0)
                {
                    $email = $this->UtilisateurModel->getUser($superviseur->id_user_backup_3)->email;
                    array_push($tabCopy, $email);
                }
            }

            $this->SendMail($destinataire, $message, $object, $tabCopy);
        }

        redirect(site_url(['CommandeTFI/detail', $id]));
    }

    private function checkStockArgAndClrBySlan($id_cmd, $data)
    {
        $produits_cmd = $this->CommandeTFIModel->listeCommandeProduit($id_cmd);
        $isValidat = true;
        $mode_preparation_livraison = $data['type_livre'];
        $posr = strpos($data["type_livre"], 'r');
        $posd = strpos($data["type_livre"], 'd');

        if ($posr !== false) {
            $mode_preparation_livraison = 6;
            $addresse_livrasion = explode(" ", $data["type_livre"])[0];
        } else if ($posd !== false) {
            $addresse_livrasion = explode(" ", $data["type_livre"])[0];
            $mode_preparation_livraison = 7;
        }
        foreach ($produits_cmd as $produit) {
            $stockVirtualARG = ($this->CommandeTFIModel->GetVirtualStockByProduit($produit->id_produit)) ? $this->CommandeTFIModel->GetVirtualStockByProduit($produit->id_produit) : 0;
            $stockVirtualCLR = ($this->CommandeTFIModel->GetVirtualStockClrByProduit($id_cmd, $produit->id_produit)) ? $this->CommandeTFIModel->GetVirtualStockClrByProduit($id_cmd, $produit->id_produit) : 0;
            $stockVirtualARGSum = ($stockVirtualARG) ? $stockVirtualARG->sum_stock_virtuel : 0;
            $stockVirtualCLRSum = ($stockVirtualCLR) ? $stockVirtualCLR->sum_stock_virtuel_clr : 0;
            if ((($mode_preparation_livraison == MODE_LIVRAISON_ARG_ENVOI_UPS ||
                        $mode_preparation_livraison == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) ||
                        $mode_preparation_livraison == MODE_LIVRAISON_ARG_RETRAIT_CLR) &&
                    ($produit->stock_arg - $stockVirtualARGSum + $produit->stock_publi) < $produit->quantite && $produit->quantite)
                ||
                (($mode_preparation_livraison == MODE_LIVRAISON_CLR_ENVOI_UPS ||
                        $mode_preparation_livraison == MODE_LIVRAISON_CLR_RSP) &&
                    ($produit->stock_clr - $stockVirtualCLRSum) < $produit->quantite && $produit->quantite)
            ) {
                $isValidat = false;
                break;
            } elseif ($mode_preparation_livraison == 6 || $mode_preparation_livraison == 7) {
                $stockdepotfournisseur = ($this->DepotFournisseurModel->getStockFournisseur($produit->id_produit, $addresse_livrasion)) ? $this->DepotFournisseurModel->getStockFournisseur($produit->id_produit, $addresse_livrasion)->stock_fournisseur : 0;
                if ($stockdepotfournisseur == 0) {
                    $isValidat = false;
                    break;
                } else if ($stockdepotfournisseur < $produit->quantite) {
                    $isValidat = false;
                    break;
                }
            }
        }
        return $isValidat;

    }

    /**
     * Methode qui permet de valider la commande
     *
     */
    public function validerSlan()
    {

        $data = $this->input->post();
        $id = $data["id_cmd"];
        $cmd = $this->CommandeTFIModel->getCommandeById($data["id_cmd"]);
        if($this->DepotModel->getIdDepotByUser($cmd->id_user)->id_depot==DEPOT_ID_DEPOT && DEBUG_ST_OUEN==1 && ($data["type_livre"]==MODE_LIVRAISON_ARG_ENVOI_UPS || $date["type_livre"]==MODE_LIVRAISON_CLR_ENVOI_UPS || $date["type_livre"]==MODE_LIVRAISON_ARG_RETRAIT_CLR )){
            // echo $data["type_livre"]."<br>";
            $data["type_livre"]=MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03;
            // echo $data["type_livre"];
        }
        $flash_livre = $data["type_livre"];
        $posr = strpos($data["type_livre"], 'r');
        $posd = strpos($data["type_livre"], 'd');
        $mode_livraison = "";
        $addresse_livrasion = "";

        $comm_fournisseur = "";
        if ($posr !== false) {
            $mode_livraison = 6;
            $addresse_livrasion = explode(" ", $data["type_livre"])[0];
        } else if ($posd !== false) {
            $addresse_livrasion = explode(" ", $data["type_livre"])[0];
            $mode_livraison = 7;
        }
        $cmd = $this->CommandeTFIModel->getCommandeById($id);

        if ($this->checkStockArgAndClrBySlan($id, $data)) {
            $this->db->trans_begin();
            $produit = "";
            if (!empty($data['commantaire_fournisseur']) && ($mode_livraison == 6 || $mode_livraison == 7)) {
                $this->CommandeTFIModel->updateCommandeForCommantaireFournisseur($id, $data['commantaire_fournisseur']);
                $comm_fournisseur = $data['commantaire_fournisseur'];
                unset($data['commantaire_fournisseur']);
            }
            unset($data['commantaire_fournisseur']);
            $liste_produits = $this->CommandeTFIModel->getProduitsWithCategorieByCommande($data['id_cmd']);
            $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th align=\"left\" style='" . style_td_th_produit . "'>Produit</th><th style='" . style_td_th . "' align=\"center\" style='" . style_td_th . "'>quantités commandées</th><th style='" . style_td_th . "' align=\"center\">quantités validées</th><th align=\"center\" style='" . style_td_th . "'>quantités refusées</th></tr>";
            foreach ($liste_produits as $key => $ele) {
                $infoProuit = $this->ProduitModel->getInfoProduit($ele->id_produit);
                if ($ele->id_categorie != ID_PRODUIT_COUTEUX && $ele->id_categorie != ID_PRODUIT_EPISPE) {
                    if ($mode_livraison == 6 || $mode_livraison == 7) {
                        $this->ProduitModel->gererStockVitruel($ele->quantite, ['id_cmd' => $data['id_cmd'], 'id_user' => $ele->id_user, 'id_produit' => $ele->id_produit], '+', $mode_livraison);
                    } elseif ($data['type_livre'] == MODE_LIVRAISON_CLR_ENVOI_UPS || $data['type_livre'] == MODE_LIVRAISON_ARG_ENVOI_UPS || $data['type_livre'] == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $data['type_livre'] == MODE_LIVRAISON_ARG_RETRAIT_CLR || $data['type_livre'] == MODE_LIVRAISON_CLR_RSP) {
                        $this->ProduitModel->gererStockVitruel($ele->quantite, ['id_cmd' => $data['id_cmd'], 'id_user' => $ele->id_user, 'id_produit' => $ele->id_produit], '+', $data['type_livre']);
                    }
                    $table .= "<tr><th style='" . style_td_th_produit . "' align=\"left\">" . $infoProuit->designation . " (<i>" . $infoProuit->reference_free . "</i>)" . "</th><th style='" . style_td_th . "' align=\"center\">" . $ele->quantite . "</th><th style='" . style_td_th . "' align=\"center\">" . $ele->quantite . "</th><th style='" . style_td_th . "' align=\"center\">0</th></tr>";
                } else {
                    for ($i = 1; $i <= $ele->quantite; $i++) {
                        if ($mode_livraison == 6 || $mode_livraison == 7) {
                            $this->ProduitModel->gererStockVitruelProduitCouteux(1, ['id_cmd' => $data['id_cmd'], 'id_user' => $ele->id_user, 'id_produit' => $ele->id_produit, 'reference' => $this->randStrGen(32)], '+', $mode_livraison);
                        } elseif ($data['type_livre'] == MODE_LIVRAISON_CLR_ENVOI_UPS || $data['type_livre'] == MODE_LIVRAISON_ARG_ENVOI_UPS || $data['type_livre'] == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $data['type_livre'] == MODE_LIVRAISON_ARG_RETRAIT_CLR || $data['type_livre'] == MODE_LIVRAISON_CLR_RSP) {
                            $this->ProduitModel->gererStockVitruelProduitCouteux(1, ['id_cmd' => $data['id_cmd'], 'id_user' => $ele->id_user, 'id_produit' => $ele->id_produit, 'reference' => $this->randStrGen(32)], '+', $data['type_livre']);
                        }
                        $table .= "<tr><th style='" . style_td_th_produit . "' align=\"left\">" . $infoProuit->designation . " (<i>" . $infoProuit->reference_free . "</i>) " . "</th><th style='" . style_td_th . "' align=\"center\">1</th><th style='" . style_td_th . "' align=\"center\">1</th><th style='" . style_td_th . "' align=\"center\">0</th></tr>";
                    }
                }

            }

            if (count($liste_produits) > 0) {
                $produit .= $table;
            }
            if ($mode_livraison == 6 || $mode_livraison == 7) {
                $livre = array('id_cmd' => $data['id_cmd'], 'type_livree' => $mode_livraison);
            } else if ($data['type_livre'] == MODE_LIVRAISON_CLR_ENVOI_UPS || $data['type_livre'] == MODE_LIVRAISON_ARG_ENVOI_UPS || $data['type_livre'] == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $data['type_livre'] == MODE_LIVRAISON_ARG_RETRAIT_CLR || $data['type_livre'] == MODE_LIVRAISON_CLR_RSP) {
                $livre = array('id_cmd' => $data['id_cmd'], 'type_livree' => $data['type_livre']);
            }
            $livrer = $this->LivrerModel->findLivreeCmd($data['id_cmd']);
            if (empty($livrer)) {
                $add = $this->LivrerModel->ajouterLivrer($livre);
            } else {
                $edit = $this->LivrerModel->modifierLivrer($data['id_cmd'], $livre);
            }
            if ($mode_livraison == 6 || $mode_livraison == 7) {
                $data_livre = array('type_livree' => $mode_livraison, 'id_depot_ups' => NULL, 'id_depot_clr' => NULL, 'id_depot_fournisseur' => $addresse_livrasion);
            } else if ($data['type_livre'] == MODE_LIVRAISON_CLR_ENVOI_UPS || $data['type_livre'] == MODE_LIVRAISON_ARG_ENVOI_UPS || $data['type_livre'] == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03)) {
                $addresse = $this->DepotModel->getIdDepotByUser($cmd->id_user)->id_depot;
                $data_livre = array('type_livree' => $data['type_livre'], 'id_depot_ups' => $addresse, 'id_depot_clr' => NULL);
            } else if ($data['type_livre'] == MODE_LIVRAISON_ARG_RETRAIT_CLR || $data['type_livre'] == MODE_LIVRAISON_CLR_RSP) {
                $addresse = $this->AdressesCLRModel->getIdAdresseByUser($cmd->id_user)->id_adresses_clr;
                $data_livre = array('type_livree' => $data['type_livre'], 'id_depot_ups' => NULL, 'id_depot_clr' => $addresse);
            }

            $this->db->update('preparation_livraison', $data_livre, array('id_cmd' => $data['id_cmd']));
            /* $livrer = $this->LivrerModel->findLivreeCmd($data['id_cmd']);
             if (empty($livrer)) {
                 $this->db->insert('preparation_livraison', $data_livre);
             } else {
                 $this->db->update('preparation_livraison', $data_livre, array('id_cmd' => $data['id_cmd']));
             }*/

            unset($data['type_livre']);
            $data["id_statcmd"] = VALIDATED;
            $data["cree_par"] = $this->user->getUser();
            $data["date_creation"] = date('Y-m-d H:i:s');
            $this->CommandeTFIModel->changeStatus($data);
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
                $message = "";
                $message .= $this->infoCommande($id);
                $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
                $message .= "<b> Valider par : </b>" . $cree_par->prenom . " " . $cree_par->nom . "  <br><br>";
                if (!empty($data["commentaire"])) {
                    $message .= "<b> Commentaire de valider  : </b>" . $data["commentaire"] . "<br><br>";
                }
                if (isset($produit)) {
                    $message .= $produit;
                }
                $destinataire = $this->getDestinataire($id);
                $reference = $this->CommandeTFIModel->getInfoCommande($id)->reference;
                $object = "[SLAM] Validation de commande : " . $reference . " - " . date('d/m/Y');
                $tabCopy = array();

                $this->SendMail($destinataire, $message, $object, $tabCopy);

                if ($mode_livraison == 6 || $mode_livraison == 7) {
                    $mode_livraison_name = (($mode_livraison == 6) ? "POINT_RELAI" : "LIV_STANDARD");
                    $fournisseur = $this->FournisseurModel->getFournisseurByCMD($cmd->id_cmd);
                    $adresse = $this->DepotFournisseurModel->getFournisseurByCMD($cmd->id_cmd);
                    $path = FCPATH . "public/csv_file/";
                    $date = date("Ymd");
                    $file = fopen($path . "F5_ODL_" . $date . "_TLC-" . $cmd->id_cmd . ".csv", 'w');
                    $destinataire = $this->UtilisateurModel->getUtilisateur($cmd->id_user);
                    fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
                    fputcsv($file, array('REF_ODL', 'SOCIETE', 'CODE_ENTITE', 'MODE_LIVRAISON', 'MODE_PAIEMENT', 'LIBELLE_LIVRAISON', 'ADRESSE_LIVRAISON', 'COMPLEMENT_ADRESSE', 'CP_LIVRAISON', 'VILLE_LIVRAISON', 'CODE_POINT_RELAI', 'EMAIL_DESTINATAIRE', 'CONTACT_DESTINATAIRE', 'TEL_CONTACT_DESTINATAIRE', 'TEL_SMS_SUIVI_COMMANDE', 'COMMENTAIRES', 'REF_ARTICLE', 'LIB_CONDT', 'QTE_CONDT', 'QTE_COMMANDE'), ";");
                    $produits = $this->CommandeTFIModel->getProduitsByCommande($cmd->id_cmd);
                    foreach ($produits as $p) {
                        $array = [];
                        if ((int)$p->quantite > 0) {
                            $produit_fournisseur = $this->FournisseurModel->getProdFournisseur($fournisseur->id_fournisseur, $p->id_produit);
                            if (strlen((String)$produit_fournisseur->ref_fournisseur) == 2)
                                $ref_f = "00" . $produit_fournisseur->ref_fournisseur;
                            else if (strlen((String)$produit_fournisseur->ref_fournisseur) == 3)
                                $ref_f = "0" . $produit_fournisseur->ref_fournisseur;
                            else
                                $ref_f = $produit_fournisseur->ref_fournisseur;
                            $array = array($fournisseur->code_fournisseur_livraison . "-" . $cmd->id_cmd, "FREE RESEAU", "411FREERESEAU", $mode_livraison_name, "VIREMENT", "FREE 95 - ARGENTEUIL", $adresse->adresse, " ", $adresse->code_postal, $adresse->ville, $adresse->code_depot_fournisseur, $destinataire->email, $destinataire->nom . " " . $destinataire->prenom, $destinataire->tel, " ", $comm_fournisseur, $ref_f, utf8_decode($produit_fournisseur->lib_condt_fournisseur), $produit_fournisseur->qte_condt, $p->quantite);
                            fputcsv($file, $array, ";");
                        }

                    }


                    fclose($file);

                }
            }
        } else {
            if ($flash_livre == MODE_LIVRAISON_CLR_ENVOI_UPS || $flash_livre == MODE_LIVRAISON_ARG_ENVOI_UPS || $flash_livre == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $flash_livre == MODE_LIVRAISON_ARG_RETRAIT_CLR || $flash_livre == MODE_LIVRAISON_CLR_RSP) {
                $this->session->set_flashdata('data_item', array('message' => 'Une erreur est survenu lors de la validation ça peut être un stock (ARG/CLR) manquant', 'class' => 'error'));
            } else {
                $this->session->set_flashdata('data_item', array('message' => 'Une erreur est survenu lors de la validation ça peut être un stock Fournisseur manquant', 'class' => 'error'));
            }
        }
        redirect(site_url(['CommandeTFI/detail', $data['id_cmd']]));
    }

    public function ChangerEtatColisToExpdie($id_colis, $id_cmd)
    {
        $data_colis["id_statutcolis"] = SHIPPED_PACKAGE;
        $this->CommandeTFIModel->changeStatusColis($id_colis, $data_colis);

        $commande_colis = $this->CommandeTFIModel->getColisByCmd($id_cmd);
        $shipped_colis_count = 0;
        $livre_colis_count = 0;
        $expedit_colis_count = 0;
        $somme_colis = 0;
        $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($id_cmd);
        $qte_total_colis = 0;
        $qte_total_cmd = 0;

        foreach ($liste_commande_produit as $key => $cmd) {
            $qte_total_colis += $this->CommandeTFIModel->getQteColisByProd($id_cmd, $cmd->id_produit);
            $qte_total_cmd += $this->CommandeTFIModel->getQteCmdByProd($id_cmd, $cmd->id_produit);
        }
        foreach ($commande_colis as $colis) {
            if ($colis->id_statutcolis == SHIPPED_PACKAGE) {
                $expedit_colis_count++;
            }
            if ($colis->id_statutcolis == DELIVERED_PACKAGE) {
                $shipped_colis_count++;
            }
            if ($colis->id_statutcolis == RECEIVED_PACKAGE) {
                $livre_colis_count++;
            }
        }

        $somme_colis = $shipped_colis_count + $livre_colis_count + $expedit_colis_count;
        if (count($commande_colis) == $somme_colis && $qte_total_colis == $qte_total_cmd && $somme_colis > 0) {
            $data_statut = array();
            $data_statut["id_cmd"] = $id_cmd;
            $data_statut["id_statcmd"] = SHIPPED;
            $data_statut["cree_par"] = $this->user->getUser();
            $data_statut["date_creation"] = date('Y-m-d H:i:s');
            $this->CommandeTFIModel->changeStatus($data_statut);
        }

        redirect(site_url(['CommandeTFI/detail', $id_cmd]));

    }

    public function ChangerEtatMultiColisToExpdie($id_colis)
    {
        $data_colis["id_statutcolis"] = SHIPPED_PACKAGE;
        $commande_courante = $this->CommandeTFIModel->getCmdByColis($id_colis);
        $all_cmd = $this->CommandeTFIModel->getOtherColisCmd($id_colis);
        $all_colis = $this->CommandeTFIModel->getOtherColisMulti($id_colis);

        foreach ($all_colis as $colis) {
            $this->CommandeTFIModel->changeStatusColis($colis->id_colis, $data_colis);
        }
        foreach ($all_cmd as $commande) {
            $commande_colis = $this->CommandeTFIModel->getColisByCmd($commande->id_cmd);
            $shipped_colis_count = 0;
            $livre_colis_count = 0;
            $expedit_colis_count = 0;
            $somme_colis = 0;
            $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($commande->id_cmd);
            $qte_total_colis = 0;
            $qte_total_cmd = 0;

            foreach ($liste_commande_produit as $key => $cmd) {
                $qte_total_colis += $this->CommandeTFIModel->getQteColisByProd($commande->id_cmd, $cmd->id_produit);
                $qte_total_cmd += $this->CommandeTFIModel->getQteCmdByProd($commande->id_cmd, $cmd->id_produit);
            }
            foreach ($commande_colis as $colis) {
                if ($colis->id_statutcolis == SHIPPED_PACKAGE) {
                    $expedit_colis_count++;
                }
                if ($colis->id_statutcolis == DELIVERED_PACKAGE) {
                    $shipped_colis_count++;
                }
                if ($colis->id_statutcolis == RECEIVED_PACKAGE) {
                    $livre_colis_count++;
                }
            }

            $somme_colis = $shipped_colis_count + $livre_colis_count + $expedit_colis_count;
            if (count($commande_colis) == $somme_colis && $qte_total_colis == $qte_total_cmd && $somme_colis > 0) {
                $data_statut = array();
                $data_statut["id_cmd"] = $commande->id_cmd;
                $data_statut["id_statcmd"] = SHIPPED;
                $data_statut["cree_par"] = $this->user->getUser();
                $data_statut["date_creation"] = date('Y-m-d H:i:s');
                $this->CommandeTFIModel->changeStatus($data_statut);
            }

        }


        redirect(site_url(['CommandeTFI/detail', $commande_courante->id_cmd]));

    }

    public function ChangerEtatColisToREExpdie($id_colis, $id_cmd, $id_user)
    {
        $this->db->trans_begin();
        $res = array('reponse' => '', 'erreur' => '0');
        $resultat = $this->LivrerModel->findLivreeCmd($id_cmd);
        $colis = $this->CommandeTFIModel->getColisById($id_colis);
        if ($resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
            $info_colis = array('id_user' => $id_user, 'Length' => $colis->longeur, 'Width' => $colis->largeur, 'Height' => $colis->hauteur, 'Weight' => $colis->poids);
            $retour = $this->UPSShipping($info_colis, $id_cmd);
            if ($retour['errors'] == 1) {
                $res = array('reponse' => $retour['results']['Description'], 'erreur' => $retour['errors']);
                $this->session->set_flashdata('item', array('id_colis' => $id_colis, 'message' => $retour['results']['Description'], 'class' => 'danger'));

            } else if ($retour['errors'] == 2) {
                $res = array('reponse' => $retour['results'], 'erreur' => $retour['errors']);
                $this->session->set_flashdata('item', array('id_colis' => $id_colis, 'message' => $retour['results'], 'class' => 'danger'));
            }
        }
        if ($res['erreur'] == 0) {
            $data_insert["id_user"] = $colis->cree_par;
            $data_insert["id_colis"] = $colis->id_colis;
            $data_insert["tracking_ups"] = $colis->tracking_ups;
            $data_insert["date_expedition"] = $colis->date_expedition;
            $this->ColisModel->historique_colis_expedier($data_insert);
            $data_colis["tracking_ups"] = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['TrackingNumber'];
            $code_barre = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage'];
            $this->GenerateImageColis($code_barre, $id_colis, 1);
            $data_colis["id_statutcolis"] = SHIPPED_PACKAGE;
            $data_colis["cree_par"] = $this->user->getUser();
            $data_colis["date_expedition"] = date('Y-m-d H:i:s');
            $data_colis["date_reception"] = NULL;
            $this->CommandeTFIModel->changeStatusColis($id_colis, $data_colis);

            $data_statut = array();
            $data_statut["id_cmd"] = $id_cmd;
            $data_statut["id_statcmd"] = SHIPPED;
            $data_statut["cree_par"] = $this->user->getUser();
            $data_statut["date_creation"] = date('Y-m-d H:i:s');
            $this->CommandeTFIModel->changeStatus($data_statut);

            $this->session->set_flashdata('item', array('id_colis' => $id_colis, 'message' => 'Le colis a été expédié avec succès.', 'class' => 'success'));
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
        }
        redirect(site_url(['CommandeTFI/detail', $id_cmd]));
    }


    public function ChangerEtatMultiColisToREExpdie($id_colis, $id_user)
    {
        $this->db->trans_begin();

        $commande_courante = $this->CommandeTFIModel->getCmdByColis($id_colis);
        $all_colis = $this->CommandeTFIModel->getOtherColisMulti($id_colis);
        $res = array('reponse' => '', 'erreur' => '0');
        $colis = $this->CommandeTFIModel->getColisById($id_colis);
        $resultat = $this->LivrerModel->findLivreeCmd($colis->id_cmd);

        if ($resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
            $info_colis = array('id_user' => $id_user, 'Length' => $colis->longeur, 'Width' => $colis->largeur, 'Height' => $colis->hauteur, 'Weight' => $colis->poids);
            $retour = $this->UPSShipping($info_colis, $colis->id_cmd);
            if ($retour['errors'] == 1) {
                $res = array('reponse' => $retour['results']['Description'], 'erreur' => $retour['errors']);
                $this->session->set_flashdata('item', array('id_colis' => $id_colis, 'message' => $retour['results']['Description'], 'class' => 'danger'));

            } else if ($retour['errors'] == 2) {
                $res = array('reponse' => $retour['results'], 'erreur' => $retour['errors']);
                $this->session->set_flashdata('item', array('id_colis' => $id_colis, 'message' => $retour['results'], 'class' => 'danger'));
            }
        }
        $colis1 = [];
        foreach ($all_colis as $cl) {
            $colis1[] = $this->ColisModel->getColisById($cl->id_colis);
        }
        foreach ($colis1 as $colis) {

            if ($res['erreur'] == 0) {
                $data_insert["id_user"] = $colis->cree_par;
                $data_insert["id_colis"] = $colis->id_colis;
                $data_insert["tracking_ups"] = $colis->tracking_ups;
                $data_insert["date_expedition"] = $colis->date_expedition;
                $this->ColisModel->historique_colis_expedier($data_insert);
                $data_colis["tracking_ups"] = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['TrackingNumber'];
                $code_barre = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage'];

                $this->GenerateImageColis($code_barre, $colis->id_colis, 1);

                $data_colis["id_statutcolis"] = SHIPPED_PACKAGE;
                $data_colis["cree_par"] = $this->user->getUser();
                $data_colis["date_expedition"] = date('Y-m-d H:i:s');
                $data_colis["date_reception"] = NULL;
                $this->CommandeTFIModel->changeStatusColis($colis->id_colis, $data_colis);
                if ($colis->id_cmd) {
                    $data_statut = array();
                    $data_statut["id_cmd"] = $colis->id_cmd;
                    $data_statut["id_statcmd"] = SHIPPED;
                    $data_statut["cree_par"] = $this->user->getUser();
                    $data_statut["date_creation"] = date('Y-m-d H:i:s');
                    $this->CommandeTFIModel->changeStatus($data_statut);
                }
                $this->session->set_flashdata('item', array('id_colis' => $id_colis, 'message' => 'Le colis a été expédié avec succès.', 'class' => 'success'));
            }
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
        }
        redirect(site_url(['CommandeTFI/detail', $commande_courante->id_cmd]));
    }


    public function ChangerEtatColisToLivree($id_colis, $id_cmd)
    {
        $data_colis["id_statutcolis"] = 4;
        $this->CommandeTFIModel->changeStatusColis($id_colis, $data_colis);

        $commande_colis = $this->CommandeTFIModel->getColisByCmd($id_cmd);
        $shipped_colis_count = 0;
        $livre_colis_count = 0;
        $somme_colis = 0;
        foreach ($commande_colis as $colis) {
            if ($colis->id_statutcolis == DELIVERED_PACKAGE) {
                $shipped_colis_count++;
            }
            if ($colis->id_statutcolis == RECEIVED_PACKAGE) {
                $livre_colis_count++;
            }
        }
        $count_produitcmd = $this->CommandeTFIModel->countProduitCmd($id_cmd);
        $count_produitcolis = $this->CommandeTFIModel->countProduitColis($id_cmd);
        $somme_colis = $shipped_colis_count + $livre_colis_count;

        if ($shipped_colis_count == count($commande_colis) && count($commande_colis) > 0 && $count_produitcmd->qte_produit_cmd == $count_produitcolis->qte_produit_colis) {
            $data_statut = array();
            $data_statut["id_cmd"] = $id_cmd;
            $data_statut["id_statcmd"] = LIVRE;
            $data_statut["cree_par"] = $this->user->getUser();
            $data_statut["date_creation"] = date('Y-m-d H:i:s');
            $this->CommandeTFIModel->changeStatus($data_statut);
        } elseif ($somme_colis == count($commande_colis) && count($commande_colis) > 0 && $somme_colis > 0 && $count_produitcmd->qte_produit_cmd == $count_produitcolis->qte_produit_colis) {
            $data_statut = array();
            $data_statut["id_cmd"] = $id_cmd;
            $data_statut["id_statcmd"] = LIVRE;
            $data_statut["cree_par"] = $this->user->getUser();
            $data_statut["date_creation"] = date('Y-m-d H:i:s');
            $this->CommandeTFIModel->changeStatus($data_statut);
        }


        redirect(site_url(['CommandeTFI/detail', $id_cmd]));
    }

    public function ChangerEtatMultiColisToLivree($id_colis)
    {
        $data_colis["id_statutcolis"] = 4;
        $commande_courante = $this->CommandeTFIModel->getCmdByColis($id_colis);
        $all_cmd = $this->CommandeTFIModel->getOtherColisCmd($id_colis);
        $all_colis = $this->CommandeTFIModel->getOtherColisMulti($id_colis);
        foreach ($all_colis as $colis) {
            $this->CommandeTFIModel->changeStatusColis($colis->id_colis, $data_colis);
        }
        foreach ($all_cmd as $commande) {

            $commande_colis = $this->CommandeTFIModel->getColisByCmd($commande->id_cmd);
            $shipped_colis_count = 0;
            $livre_colis_count = 0;
            $somme_colis = 0;
            foreach ($commande_colis as $colis) {
                if ($colis->id_statutcolis == DELIVERED_PACKAGE) {
                    $shipped_colis_count++;
                }
                if ($colis->id_statutcolis == RECEIVED_PACKAGE) {
                    $livre_colis_count++;
                }
            }
            $count_produitcmd = $this->CommandeTFIModel->countProduitCmd($commande->id_cmd);
            $count_produitcolis = $this->CommandeTFIModel->countProduitColis($commande->id_cmd);
            $somme_colis = $shipped_colis_count + $livre_colis_count;
            if ($shipped_colis_count == count($commande_colis) && count($commande_colis) > 0 && $count_produitcmd->qte_produit_cmd == $count_produitcolis->qte_produit_colis) {
                $data_statut = array();
                $data_statut["id_cmd"] = $commande->id_cmd;
                $data_statut["id_statcmd"] = LIVRE;
                $data_statut["cree_par"] = $this->user->getUser();
                $data_statut["date_creation"] = date('Y-m-d H:i:s');
                $this->CommandeTFIModel->changeStatus($data_statut);
            } elseif ($somme_colis == count($commande_colis) && count($commande_colis) > 0 && $somme_colis > 0 && $count_produitcmd->qte_produit_cmd == $count_produitcolis->qte_produit_colis) {
                $data_statut = array();
                $data_statut["id_cmd"] = $commande->id_cmd;
                $data_statut["id_statcmd"] = LIVRE;
                $data_statut["cree_par"] = $this->user->getUser();
                $data_statut["date_creation"] = date('Y-m-d H:i:s');
                $this->CommandeTFIModel->changeStatus($data_statut);
            }


        }


        redirect(site_url(['CommandeTFI/detail', $commande_courante->id_cmd]));

    }

    /**
     * Methode qui permet de refuer la commande
     *
     */
    public function refuser()
    {
        $data = $this->input->post();
        $equipe = $this->input->post("equipe");
        $id = $data["id_cmd"];
        $this->db->trans_begin();
        if (has_permission(CDT_PROFILE))
            $data["id_statcmd"] = REFUSED_CDT;
        elseif (has_permission(CDP_PROFILE))
            $data["id_statcmd"] = REFUSED_CDP;
        else
            $data["id_statcmd"] = REFUSED;
        $data["cree_par"] = $this->user->getUser();
        $data["date_creation"] = date('Y-m-d H:i:s');
        $this->CommandeTFIModel->changeStatus($data);
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $message = "";
            $message .= $this->infoCommande($data["id_cmd"]);
            $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
            $message .= "<b> Refusée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . "  <br><br>";
            if (!empty($data["commentaire"])) {
                $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
            }
            $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($data["id_cmd"]);

            $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">quantités commandées</th><th style='" . style_td_th . "' align=\"center\">quantités validées</th><th style='" . style_td_th . "' align=\"center\">quantités refusées</th></tr>";
            foreach ($liste_commande_produit as $produit) {
                $table .= "<tr><td style='" . style_td_th_produit . "' align=\"left\">" . $produit->designation . " (<i>" . $produit->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . $produit->quantite . "</td><td style='" . style_td_th . "' align=\"center\"> 0 </td><td style='" . style_td_th . "' align=\"center\">" . $produit->quantite . "</td></tr>";
            }
            $table .= "</table><br>";
            if (count($liste_commande_produit) > 0) {
                $message .= $table;
            }
            $destinataire = $this->getDestinataire($data["id_cmd"]);
            $reference = $this->CommandeTFIModel->getInfoCommande($data["id_cmd"])->reference;
            $object = "[SLAM] Refus de commande : " . $reference . " - " . date('d/m/Y');
            $tabCopy = array();
            $this->SendMail($destinataire, $message, $object, $tabCopy);
        }
        if (has_permission(CDP_PROFILE)) {
            $getNextIDCMD = $this->CommandeTFIModel->getNextIdCmd($this->user->getUser(), $id, 'MIN');
            $getpreviousIDCMD = $this->CommandeTFIModel->getNextIdCmd($this->user->getUser(), $id, 'MAX');
            if ($getNextIDCMD) {
                if ($equipe)
                    redirect(site_url(['CommandeTFI/liste', WAIT_VALIDATION_BY_EQUIPE_CDP]));
                else {
                    $id_suivant = $getNextIDCMD->id;
                    redirect(site_url(['CommandeTFI/detail', $id_suivant]));
                }
            } elseif ($getpreviousIDCMD) {
                if ($equipe)
                    redirect(site_url(['CommandeTFI/liste', WAIT_VALIDATION_BY_EQUIPE_CDP]));
                else {
                    $id_suivant = $getpreviousIDCMD->id;
                    redirect(site_url(['CommandeTFI/detail', $id_suivant]));
                }
            } else {
                if ($equipe)
                    redirect(site_url(['CommandeTFI/liste', WAIT_VALIDATION_BY_EQUIPE_CDP]));
                else
                    redirect(site_url(['CommandeTFI/liste', WAIT_VALIDATION_CDP]));
            }

        } else {
            redirect(site_url(['CommandeTFI/detail', $data["id_cmd"]]));
        }


    }

    public function getstockCLR($id_user)
    {
        $id_adresse_clr = $this->UserAdressesModel->getAdresseBYUser($id_user);
    }

    public function getProduitStockCLR($id_user, $id_produit)
    {
        $id_adresse_clr = $this->UserAdressesModel->getAdresseBYUser(array('id_user' => $id_user));
        $quantite_produit = $this->StockCLRModel->getStockCLRProduit(array('id_adresses_clr' => $id_adresse_clr->id_adresses_clr, 'id_produit' => $id_produit));
        return $quantite_produit;
    }

    /**
     * Methode qui permet d'expedier la commande passée en argument
     * @param int $id
     */


    public function shipTo($url, $params)
    {

        $headers = array();
        $headers[] = 'Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept';
        $headers[] = 'Access-Control-Allow-Methods: POST,GET';
        $headers[] = 'Access-Control-Allow-Origin: *';
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 100);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $response = curl_exec($ch);

        if ($response === false) {
            echo curl_error($ch);
        }

        return $response;
    }

    public function UpdateModeLivraison(){

        if(DEBUG_ST_OUEN==1){
            /* echo "<pre>";
             var_dump($this->CommandeTFIModel->getModeLivreUPS());
             echo "</pre>";*/
            $getModeLivraison=$this->CommandeTFIModel->getModeLivreUPS();
            foreach ($getModeLivraison as $modeLivrasion){
                $this->db->update('preparation_livraison',array('type_livree'=>MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03,'id_depot_ups'=>$modeLivrasion->id_depot,'id_depot_clr'=>NULL),array('id'=>$modeLivrasion->id));
            }
        }

    }

    public function expedier($id)
    {
        $this->db->trans_start();
        $res = array('reponse' => '', 'erreur' => '0');
        $data = $this->input->post();

        $getModeLivraison = $this->LivrerModel->findLivreeCmd($id);
        $cmd = $this->CommandeTFIModel->getCommandeById($id);
        $countColis= $this->CommandeTFIModel->getcountColis($id);
        if($this->DepotModel->getIdDepotByUser($cmd->id_user)->id_depot==DEPOT_ID_DEPOT && $countColis==0 && DEBUG_ST_OUEN==1 && ($getModeLivraison->type_livree==MODE_LIVRAISON_ARG_ENVOI_UPS || $getModeLivraison->type_livree==MODE_LIVRAISON_CLR_ENVOI_UPS || $getModeLivraison->type_livree==MODE_LIVRAISON_ARG_RETRAIT_CLR )){
            $addresse = $this->DepotModel->getIdDepotByUser($cmd->id_user)->id_depot;
            $data_livre = array('id_cmd' => $id, 'type_livree' => MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03, 'id_depot_ups' => $addresse, 'id_depot_clr' => NULL);
            if (empty($getModeLivraison)) {
                $add = $this->LivrerModel->ajouterLivrer($data_livre);
            } else {
                $edit = $this->LivrerModel->modifierLivrer($id, $data_livre);
            }
        }
        $resultat = $this->LivrerModel->findLivreeCmd($id);
        if ($resultat->type_livree == MODE_LIVRAISON_CLR_RSP || $resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS) {
            $id_adresse_clr = $this->UserAdressesModel->getAdresseBYUser(array('id_user' => $data['id_tfi']));
            if (empty($id_adresse_clr)) {
                $res = array('reponse' => 'il n\'y a pas adresse clr à cette employée', 'erreur' => '2');
            }
        }
        if (isset($data["productsToAdd"])) {
            $productsToAdd = $data["productsToAdd"];
            $this->trierListeProduits($productsToAdd);
            $this->addExtratCountField($productsToAdd);
        } else
            $productsToAdd = [];

        foreach ($productsToAdd as $key => $prod) {
            $qte_colis = $this->CommandeTFIModel->getQteColisByProd($id, $prod["id_produit"]);
            $qte_cmd = $this->CommandeTFIModel->getQteCmdByProd($id, $prod["id_produit"]);
            $qte_manquante = $qte_cmd - $qte_colis;
            $produit = $this->ProduitModel->getProduit($prod["id_produit"]);
            if (!empty($id_adresse_clr)) {
                $quantite_produit = $this->StockCLRModel->getStockCLRProduit(array('id_adresses_clr' => $id_adresse_clr->id_adresses_clr, 'id_produit' => $prod["id_produit"]));
                if (empty($quantite_produit)) {
                    $quantiteStockCLR = 0;
                } else {
                    $quantiteStockCLR = $quantite_produit->quantite;
                }
            } else {
                $quantiteStockCLR = 0;
            }

            if ($resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
                if (isset($prod["count"]) && ((intval($prod["count"]) > 1 && ($prod["count"] > $qte_manquante || $prod["count"] > $produit->stock_arg))
                        || (intval($prod["count"]) == 1 && ($prod["quantite"] > $qte_manquante || $prod["quantite"] > $produit->stock_arg + $produit->stock_publi)))
                ) {
                    $productsToAdd = [];
                    break;
                }
            } elseif ($resultat->type_livree == MODE_LIVRAISON_CLR_RSP || $resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS) {
                if (isset($prod["count"]) && ((intval($prod["count"]) > 1 && $prod["count"] > $quantiteStockCLR)
                        || (intval($prod["count"]) == 1 && $prod["quantite"] > $quantiteStockCLR))
                ) {
                    $productsToAdd = [];
                    break;
                }
            } elseif ($prod["quantite"] <= 0)
                unset($productsToAdd[$key]);

        }
        if (count($productsToAdd) > 0) {
            if ($resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
                $info_colis = array('id_user' => $data["id_tfi"], 'Length' => $data['longeur'], 'Width' => $data['largeur'], 'Height' => $data['hauteur'], 'Weight' => $data['poids']);
                $retour = $this->UPSShipping($info_colis, $id);


                if ($retour['errors'] == 1) {
                    $res = array('reponse' => $retour['results']['Description'], 'erreur' => $retour['errors']);
                } else if ($retour['errors'] == 2) {
                    $res = array('reponse' => $retour['results'], 'erreur' => $retour['errors']);
                }
            }

            if ($res['erreur'] == 0) {
                if (count($productsToAdd) > 0) {
                    $tracking_ups = $this->randStrGen(12);
                    $commentaire = $data["commentaire"];
                    $data_colis["poids"] = $data["poids"];
                    $data_colis["largeur"] = $data["largeur"];
                    $data_colis["longeur"] = $data["longeur"];
                    $data_colis["hauteur"] = $data["hauteur"];
                    $data_colis["hauteur"] = $data["hauteur"];
                    $data_colis["date_expedition"] = date('Y-m-d H:i:s');
                    $data_colis["comment_expedition"] = $commentaire;
                    $data_colis["cree_par"] = $this->user->getUser();
                    $data_colis["id_cmd"] = $id;
                    $data_statut["id_cmd"] = $id;
                    $data_statut["cree_par"] = $this->user->getUser();
                    $data_statut["date_creation"] = date('Y-m-d H:i:s');
                    if ($resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
                        $data_colis["tracking_ups"] = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['TrackingNumber'];
                        $code_barre = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage'];
                        $data_colis["id_statutcolis"] = COLIS_PREPARER;
                        if ($resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
                            $data_colis["scan_code"] = $tracking_ups;
                        }
                    } elseif ($resultat->type_livree == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $resultat->type_livree == MODE_LIVRAISON_CLR_RSP) {
                        $data_colis["tracking_ups"] = '';
                        $data_colis["scan_code"] = $tracking_ups;
                        $data_colis["id_statutcolis"] = SHIPPED_LIVRE;
                    } elseif ($resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS) {
                        $data_colis["tracking_ups"] = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['TrackingNumber'];
                        $code_barre = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage'];
                        $data_colis["scan_code"] = $tracking_ups;
                        $data_colis["id_statutcolis"] = COLIS_PREPARER;
                    }
                    $id_colis = $this->CommandeTFIModel->ajouterColis($data_colis);
                    if (isset($code_barre)) {
                        $this->GenerateImageColis($code_barre, $id_colis);
                    }
                    $dataElementsColis1 = array();
                    foreach ($productsToAdd as $key => $prod) {
                        $productsToAdd[$key]["id_colis"] = $id_colis;
                        $produit = $this->ProduitModel->getProduit($prod["id_produit"]);
                        if ($resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {

                            $stock_arg = (intval($produit->stock_arg) - intval($prod["quantite"]));
                            $arg_produit_data = array();
                            if( $stock_arg < 0) {

                                $stock_publi = $produit->stock_publi + $stock_arg;
                                $arg_produit_data = array(
                                    'stock_arg' => 0,
                                    'stock_publi' => $stock_publi,
                                );
                            }

                            else {

                                $arg_produit_data = array('stock_arg' => $stock_arg);
                            }
                            // $arg_produit_data = array('stock_arg' => $stock_arg);

                            $this->ProduitModel->modifier($prod["id_produit"], $arg_produit_data);

                        } elseif ($resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_CLR_RSP) {
                            $produitstockclr = $this->getProduitStockCLR($data['id_tfi'], $prod["id_produit"]);
                            if (empty($produitstockclr)) {
                                $quantiteStockCLR = 0;
                            } else {
                                $quantiteStockCLR = $produitstockclr->quantite;
                                $stock_clr = $quantiteStockCLR - $prod["quantite"];
                                $this->StockCLRModel->modifierStockProduit(array('quantite' => $stock_clr), $produitstockclr->id);
                            }
                        }
                        $stock_user = $this->CommandeTFIModel->getStockTFI($data["id_tfi"], $prod["id_produit"], $id, $prod["ancien_reference"]);
                        if ($stock_user->id_user == null) {
                            $data_insert['id_produit'] = $prod["id_produit"];
                            $data_insert['id_user'] = $data["id_tfi"];
                            $data_insert['stock_transit'] = $prod["quantite"];
                            $data_insert['reference'] = $prod["reference"];
                        } else {
                            $data_update['id_produit'] = $prod["id_produit"];
                            $data_update['id_user'] = $data["id_tfi"];
                            $data_update['stock_transit'] = $stock_user->stock_transit + $prod["quantite"];
                            if ($produit->id_categorie != ID_PRODUIT_EPISPE)
                                $data_update['reference'] = $prod["reference"];
                            else
                                $data_update['reference'] = $prod["ancien_reference"];
                            $update_produit_tfi = $this->CommandeTFIModel->updateStockTFI($stock_user->id, $data_update);
                        }
                        if ($produit->id_categorie != ID_PRODUIT_COUTEUX && $produit->id_categorie != ID_PRODUIT_EPISPE)
                            $this->ProduitModel->gererStockVitruel($prod["quantite"], ['id_cmd' => $id, 'id_user' => $data["id_tfi"], 'id_produit' => $prod["id_produit"], 'id' => $stock_user->id], '-', $resultat->type_livree);
                        else {
                            if ($produit->id_categorie != ID_PRODUIT_EPISPE)
                                $this->ProduitModel->gererStockVitruelProduitCouteux($prod["quantite"], ['id_cmd' => $id, 'id_user' => $data["id_tfi"], 'id_produit' => $prod["id_produit"], 'reference' => $prod["reference"]], '-', $resultat->type_livree);
                            else
                                $this->ProduitModel->gererStockVitruelProduitCouteux($prod["quantite"], ['id_cmd' => $id, 'id_user' => $data["id_tfi"], 'id_produit' => $prod["id_produit"], 'reference' => $prod["ancien_reference"]], '-', $resultat->type_livree);
                        }

                        $dataElementColis = array();
                        $dataElementColis['id_colis'] = $id_colis;
                        $dataElementColis['id_produit'] = $prod['id_produit'];
                        $dataElementColis['quantite'] = $prod['quantite'];
                        if ($produit->id_categorie != ID_PRODUIT_EPISPE) {
                            $dataElementColis['reference'] = $prod['reference'];
                            $dataElementColis['reference_epi'] = "";
                        } else {
                            $dataElementColis['reference'] = $prod['ancien_reference'];
                            $dataElementColis['reference_epi'] = $prod['reference'];
                        }
                        $dataElementsColis1[] = $dataElementColis;

                    }

                    $this->CommandeTFIModel->ajouterElementColis($dataElementsColis1);

                    $id_status = $this->CommandeTFIModel->getLastStatus($id)->id_statcmd;
                    $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($id);
                    $qte_total_colis = 0;
                    $qte_total_cmd = 0;

                    foreach ($liste_commande_produit as $key => $cmd) {
                        $qte_total_colis += $this->CommandeTFIModel->getQteColisByProd($id, $cmd->id_produit);
                        $qte_total_cmd += $this->CommandeTFIModel->getQteCmdByProd($id, $cmd->id_produit);
                    }

                    if ($qte_total_cmd == $qte_total_colis) {
                        if ($resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
                            $data_statut["id_statcmd"] = CMD_PREPARER;
                        } else if ($resultat->type_livree == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $resultat->type_livree == MODE_LIVRAISON_CLR_RSP) {
                            $data_statut["id_statcmd"] = CMD_PREPARER;
                        }

                        $this->CommandeTFIModel->changeStatus($data_statut);
                        if ($resultat->type_livree == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $resultat->type_livree == MODE_LIVRAISON_CLR_RSP) {
                            $data_statut["id_statcmd"] = LIVRE;
                            $this->CommandeTFIModel->changeStatus($data_statut);
                        }
                    } elseif ($id_status != MISSING_STOCK) {
                        $data_statut["id_statcmd"] = MISSING_STOCK;
                        $this->CommandeTFIModel->changeStatus($data_statut);
                    }
                    $this->session->set_flashdata('item', array('id_colis' => $id_colis, 'message' => 'Le colis a été expédié avec succès.', 'class' => 'success'));
                    if ($resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
                        $res = array('respone' => $retour['results'], 'erreur' => $retour['errors'], 'id_colis' => $id_colis);
                    } elseif ($resultat->type_livree == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03) || $resultat->type_livree == MODE_LIVRAISON_CLR_RSP) {
                        $res = array('respone' => 'a ete expediter', 'erreur' => '0', 'id_colis' => $id_colis);
                    }
                    echo json_encode($res);
                } else {
                    echo json_encode($res);
                    $this->session->set_flashdata('item', array('message' => 'Une erreur est survenu lors de l\'expédition ça peut être un stock manquant', 'class' => 'error'));
                }
            } else {
                echo json_encode($res);
            }
        } else {
            echo json_encode($res);
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $message = "";
            $message .= $this->infoCommande($id);
            $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
            $message .= "<b> Preparée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . "  <br><br>";
            $mode = "";
            if ($resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR || $resultat->type_livree == MODE_LIVRAISON_CLR_RSP) {
                $address_clr = $this->AdressesCLRModel->getIdAdresseByUser($data["id_tfi"]);
                $address = $address_clr->nom . ' ' . $address_clr->adresse . ' ' . $address_clr->ville . ' ' . $address_clr->code_postal;
                if ($resultat->type_livree == MODE_LIVRAISON_CLR_RSP) {
                    $mode .= "<b>Point de retrait :</b> " . $address . " <br><br> <b> Tracking : </b> Retrait sur place";
                } else {
                    $code_colis = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['TrackingNumber'];
                    $mode .= " <b>Point de retrait :</b> " . $address . " <br><br> <b> Tracking : </b><a target='_blank' href='https://wwwapps.ups.com/WebTracking/track?loc=fr_FR&trackNums=" . $code_colis . "'>" . $code_colis . "</a>";
                }
            } else if ($resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS) {
                $code_colis = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['TrackingNumber'];
                $adresse_ups = $this->DepotModel->getIdDepotByUser($data["id_tfi"]);
                $address = $adresse_ups->nom . ' ' . $adresse_ups->adresse . ' ' . $adresse_ups->ville . ' ' . $adresse_ups->code_postal;
                $mode .= "<b>Point de retrait :</b> " . $address . " <br><br> <b> Tracking : </b><a target='_blank' href='https://wwwapps.ups.com/WebTracking/track?loc=fr_FR&trackNums=" . $code_colis . "'>" . $code_colis . "</a>";
            } elseif ($resultat->type_livree == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03)) {
                $mode .= "<b>Point de retrait :</b> Argenteuil  <br><br> <b>Tracking : </b>Retrait sur place";
            }

            if (!empty($data["commentaire"])) {
                $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
            }
            $message .= $mode . "<br><br>";
            $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité expediée</th></tr>";
            foreach ($productsToAdd as $key => $prod) {
                $table .= "<tr><td style='" . style_td_th_produit . "' align=\"left\">" . $this->ProduitModel->getInfoProduit($prod['id_produit'])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($prod['id_produit'])->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . $prod['quantite'] . "</td></tr>";
            }
            $table .= "</table><br>";
            if (count($productsToAdd) > 0) {
                $message .= $table;
            }
            $reference = $this->CommandeTFIModel->getInfoCommande($id)->reference;
            if ($resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR || $resultat->type_livree == MODE_LIVRAISON_CLR_ENVOI_UPS) {
                $object = "[SLAM] Colis expedié : " . $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['TrackingNumber'] . "(" . $reference . ") - " . date('d/m/Y');
            } else {
                $object = "[SLAM] Colis expedié : " . $reference . " - " . date('d/m/Y');
            }


            $destinataire = $this->getDestinataire($id);
            $tabCopy = array();

            $this->SendMail($destinataire, $message, $object, $tabCopy);
        }

    }

    public function UPSShipping($info_colis, $id_cmd)
    {

        if ($info_colis['Length'] == 0 && $info_colis['Width'] == 0 && $info_colis['Height'] == 0) {
            $package = array(
                'Description' => 'Description',
                'Packaging' => array(
                    'Code' => '02',
                    'Description' => 'Description'
                ),
                'Dimensions' => array(
                    'UnitOfMeasurement' => array(
                        'Code' => 'CM',
                        'Description' => 'Centim'
                    ),
                    'Length' => $info_colis['Length'],
                    'Width' => $info_colis['Width'],
                    'Height' => $info_colis['Height']
                ),
                'PackageWeight' => array(
                    'UnitOfMeasurement' => array(
                        'Code' => 'KGS',
                        'Description' => 'Kilo'
                    ),
                    'Weight' => $info_colis['Weight']
                )
            );
        } else {
            $package = array(
                'Description' => 'Description',
                'Packaging' => array(
                    'Code' => '02',
                    'Description' => 'Description'
                ),
                'Dimensions' => array(
                    'UnitOfMeasurement' => array(
                        'Code' => 'CM',
                        'Description' => 'Centim'
                    ),
                    'Length' => $info_colis['Length'],
                    'Width' => $info_colis['Width'],
                    'Height' => $info_colis['Height']
                ),
                'PackageWeight' => array(
                    'UnitOfMeasurement' => array(
                        'Code' => 'KGS',
                        'Description' => 'Kilo'
                    ),
                    'Weight' => $info_colis['Weight']
                )
            );
        }
        // $depot=$this->DepotModel->getIdDepotByUser($info_colis['id_user']);
        $modeLivrer = $this->LivrerModel->findLivreeCmd($id_cmd)->type_livree;
        if ($modeLivrer == MODE_LIVRAISON_CLR_ENVOI_UPS || $modeLivrer == MODE_LIVRAISON_ARG_ENVOI_UPS) {
            $depot = $this->DepotModel->getIdDepotByUser($info_colis['id_user']);
            if ($depot->adresse_api != NULL) {
                $adresse = explode("\n", $depot->adresse_api);
            } else {
                $adresse = $depot->adresse;
            }
        } elseif ($modeLivrer == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
            $depot = $this->AdressesCLRModel->getIdAdresseByUser($info_colis['id_user']);
            $adresse = $depot->adresse;
        }

        if (!empty($depot)) {
            if (empty($depot->telephone)) {
                $shipTo = array(
                    'Name' => $depot->nom,
                    'AttentionName' => $depot->nom_user . ' ' . $depot->prenom,
                    'Address' => array(
                        'AddressLine' => $adresse,
                        'City' => $depot->ville,
                        'StateProvinceCode' => '',
                        'PostalCode' => $depot->code_postal,
                        'CountryCode' => 'FR'
                    )
                );
            } else {
                $shipTo = array(
                    'Name' => $depot->nom,
                    'AttentionName' => $depot->nom_user . ' ' . $depot->prenom,
                    'Phone' => array(
                        'Number' => $depot->telephone,
                    ),
                    'Address' => array(
                        'AddressLine' => $adresse,
                        'City' => $depot->ville,
                        'StateProvinceCode' => '',
                        'PostalCode' => $depot->code_postal,
                        'CountryCode' => 'FR'
                    )
                );
            }

            if (has_permission(CLR_PROFILE)) {
                $adresse_user = $this->AdressesCLRModel->getIdAdresseByUser($this->user->getUser());
                $shipFrom = array(
                    'Name' => $adresse_user->nom_user . ' ' . $adresse_user->prenom,
                    'AttentionName' => $adresse_user->nom_user . ' ' . $adresse_user->prenom,
                    'Phone' => array(
                        'Number' => $adresse_user->telephone,
                    ),
                    'FaxNumber' => $adresse_user->fax,
                    'Address' => array(
                        'AddressLine' => $adresse_user->adresse,
                        'City' => $adresse_user->ville,
                        'StateProvinceCode' => '',
                        'PostalCode' => $adresse_user->code_postal,
                        'CountryCode' => 'FR'
                    )
                );
            } else {
                $shipFrom = array(
                    'Name' => Name_FROM,
                    'AttentionName' => ATTENTION_NAME_FROM,
                    'Phone' => array(
                        'Number' => NUMBER_FROM,
                    ),
                    'FaxNumber' => FAX_NUMBER_FROM,
                    'Address' => array(
                        'AddressLine' => ADDRESS_LINE_FROM,
                        'City' => CITY_FROM,
                        'StateProvinceCode' => STATE_PROVINCE_CODE_FROM,
                        'PostalCode' => POSTAL_CODE_FROM,
                        'CountryCode' => COUNTRY_CODE_FROM
                    )
                );
            }
            $ups_array = array(
                'UPSSecurity' => array(
                    'UsernameToken' => array(
                        'Username' => USERNAME,
                        'Password' => PASSWORD
                    ),
                    'ServiceAccessToken' => array(
                        'AccessLicenseNumber' => ACCES_LICENSE_NUMBRE
                    )
                ),
                'ShipmentRequest' => array(
                    'Request' => array(
                        'RequestOption' => 'validate',
                        'TransactionReference' => array(
                            'CustomerContext' => 'Your Customer Context'
                        )
                    ),
                    'Shipment' => array(
                        'Description' => 'Description',
                        'Shipper' => array(
                            'Name' => NAME,
                            'AttentionName' => ATTENTION_NAME,
                            'TaxIdentificationNumber' => TAX_IDENTIFICATION_NUMBER,
                            'Phone' => array(
                                'Number' => NUMBER,
                                'Extension' => '1'
                            ),
                            'ShipperNumber' => ACCOUNT_NUMBER,
                            'FaxNumber' => FAX_NUMBER,
                            'Address' => array(
                                'AddressLine' => ADDRESS_LINE,
                                'City' => CITY,
                                'StateProvinceCode' => STATE_PROVINCE_CODE,
                                'PostalCode' => POSTAL_CODE,
                                'CountryCode' => COUNTRY_CODE
                            )
                        ),
                        'ShipTo' => $shipTo,
                        'ShipFrom' => $shipFrom,
                        'PaymentInformation' => array(
                            'ShipmentCharge' => array(
                                'Type' => '01',
                                'BillShipper' => array(
                                    'AccountNumber' => ACCOUNT_NUMBER
                                )
                            )
                        ),
                        'Service' => array(
                            'Code' => '011',
                            'Description' => 'UPS STANDARD'
                        ),
                        'Package' => $package
                    ),
                    'LabelSpecification' => array(
                        'LabelImageFormat' => array(
                            'Code' => 'GIF',
                            'Description' => 'GIF'
                        ),
                        'HTTPUserAgent' => 'Mozilla/4.5'
                    )
                ),
            );
            $params = json_encode($ups_array);

            $results_json = $this->shipTo(URL_UPS_SHIPPING_TEST, $params);

            $results = json_decode($results_json, true);

            return $this->results_shipping($results);

        } else {
            $res = array('results' => 'adresse depot/CLR de cette employée n\'existe pas', 'errors' => 2);
            return $res;
        }

    }
    public function generateTracking($info_colis,$adresse_user)
    {

        if ($info_colis['Length'] == 0 && $info_colis['Width'] == 0 && $info_colis['Height'] == 0) {
            $package = array(
                'Description' => 'Description',
                'Packaging' => array(
                    'Code' => '02',
                    'Description' => 'Description'
                ),
                'Dimensions' => array(
                    'UnitOfMeasurement' => array(
                        'Code' => 'CM',
                        'Description' => 'Centim'
                    ),
                    'Length' => $info_colis['Length'],
                    'Width' => $info_colis['Width'],
                    'Height' => $info_colis['Height']
                ),
                'PackageWeight' => array(
                    'UnitOfMeasurement' => array(
                        'Code' => 'KGS',
                        'Description' => 'Kilo'
                    ),
                    'Weight' => $info_colis['Weight']
                )
            );
        } else {
            $package = array(
                'Description' => 'Description',
                'Packaging' => array(
                    'Code' => '02',
                    'Description' => 'Description'
                ),
                'Dimensions' => array(
                    'UnitOfMeasurement' => array(
                        'Code' => 'CM',
                        'Description' => 'Centim'
                    ),
                    'Length' => $info_colis['Length'],
                    'Width' => $info_colis['Width'],
                    'Height' => $info_colis['Height']
                ),
                'PackageWeight' => array(
                    'UnitOfMeasurement' => array(
                        'Code' => 'KGS',
                        'Description' => 'Kilo'
                    ),
                    'Weight' => $info_colis['Weight']
                )
            );
        }

        $depot = $this->DepotModel->getIdDepotByUser($info_colis['id_user']);
        if ($depot->adresse_api != NULL) {
            $adresse = explode("\n", $depot->adresse_api);
        } else {
            $adresse = $depot->adresse;
        }


        if (!empty($depot)) {
            if (empty($depot->telephone)) {
                $shipTo = array(
                    'Name' => $depot->nom,
                    'AttentionName' => $depot->nom_user . ' ' . $depot->prenom,
                    'Address' => array(
                        'AddressLine' => $adresse,
                        'City' => $depot->ville,
                        'StateProvinceCode' => '',
                        'PostalCode' => $depot->code_postal,
                        'CountryCode' => 'FR'
                    )
                );
            } else {
                $shipTo = array(
                    'Name' => $depot->nom,
                    'AttentionName' => $depot->nom_user . ' ' . $depot->prenom,
                    'Phone' => array(
                        'Number' => $depot->telephone,
                    ),
                    'Address' => array(
                        'AddressLine' => $adresse,
                        'City' => $depot->ville,
                        'StateProvinceCode' => '',
                        'PostalCode' => $depot->code_postal,
                        'CountryCode' => 'FR'
                    )
                );
            }

            $shipFrom = array(
                'Name' => $adresse_user->nom_user . ' ' . $adresse_user->prenom,
                'AttentionName' => $adresse_user->nom_user . ' ' . $adresse_user->prenom,
                'Phone' => array(
                    'Number' => $adresse_user->telephone,
                ),
                'FaxNumber' => $adresse_user->fax,
                'Address' => array(
                    'AddressLine' => $adresse_user->adresse,
                    'City' => $adresse_user->ville,
                    'StateProvinceCode' => '',
                    'PostalCode' => $adresse_user->code_postal,
                    'CountryCode' => 'FR'
                )
            );

            $ups_array = array(
                'UPSSecurity' => array(
                    'UsernameToken' => array(
                        'Username' => USERNAME,
                        'Password' => PASSWORD
                    ),
                    'ServiceAccessToken' => array(
                        'AccessLicenseNumber' => ACCES_LICENSE_NUMBRE
                    )
                ),
                'ShipmentRequest' => array(
                    'Request' => array(
                        'RequestOption' => 'validate',
                        'TransactionReference' => array(
                            'CustomerContext' => 'Your Customer Context'
                        )
                    ),
                    'Shipment' => array(
                        'Description' => 'Description',
                        'Shipper' => array(
                            'Name' => $adresse_user->nom_user . ' ' . $adresse_user->prenom,
                            'AttentionName' => $adresse_user->nom_user . ' ' . $adresse_user->prenom,
                            'TaxIdentificationNumber' => TAX_IDENTIFICATION_NUMBER,
                            'Phone' => array(
                                'Number' => $adresse_user->telephone,
                                'Extension' => '1'
                            ),
                            'ShipperNumber' => ACCOUNT_NUMBER,
                            'FaxNumber' => $adresse_user->fax,
                            'Address' => array(
                                'AddressLine' => $adresse_user->adresse,
                                'City' => $adresse_user->ville,
                                'StateProvinceCode' => STATE_PROVINCE_CODE,
                                'PostalCode' => $adresse_user->code_postal,
                                'CountryCode' => COUNTRY_CODE
                            )
                        ),
                        'ShipTo' => $shipTo,
                        'ShipFrom' => $shipFrom,
                        'PaymentInformation' => array(
                            'ShipmentCharge' => array(
                                'Type' => '01',
                                'BillShipper' => array(
                                    'AccountNumber' => ACCOUNT_NUMBER
                                )
                            )
                        ),
                        'Service' => array(
                            'Code' => '011',
                            'Description' => 'UPS STANDARD'
                        ),
                        'Package' => $package
                    ),
                    'LabelSpecification' => array(
                        'LabelImageFormat' => array(
                            'Code' => 'GIF',
                            'Description' => 'GIF'
                        ),
                        'HTTPUserAgent' => 'Mozilla/4.5'
                    )
                ),
            );
            $params = json_encode($ups_array);

            $results_json = $this->shipTo(URL_UPS_SHIPPING_TEST, $params);

            $results = json_decode($results_json, true);

            return $this->results_shipping($results);

        } else {
            $res = array('results' => 'adresse depot de cette employée n\'existe pas', 'errors' => 2);
            return $res;
        }

    }

    public function getTracking(){
        $info_colis = array('id_user' => 102, 'Length' => '3', 'Width' => '3', 'Height' => '3', 'Weight' => '15');
        $adresse_user = $depot = $this->DepotModel->getIdDepotByUser($this->user->getUser());

        $tracking=$this->generateTracking($info_colis,$adresse_user);
        echo "<pre>";
        var_dump($this->results_shipping($tracking));
        echo "</pre>";
        $getRes=$this->results_shipping($tracking);
        $data_colis = $getRes['results']['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['TrackingNumber'];
        $code_barre = $getRes['results']['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage'];
        echo $data_colis."<br>";
        echo $code_barre."<br>";
        $this->GenerateImageColis($code_barre, 12456);
    }

    public function results_shipping($results)
    {
        $res = array('results' => $results, 'errors' => 0);
        if (isset($results['Fault']['detail']['Errors']['ErrorDetail']['PrimaryErrorCode']['Code'])) {
            $code = $results['Fault']['detail']['Errors']['ErrorDetail']['PrimaryErrorCode'];
            $res['results'] = $results['Fault']['detail']['Errors']['ErrorDetail']['PrimaryErrorCode'];
            $res['errors'] = 1;
            return $res;
        } else {
            return $res;
        }
    }

    public function recevoir()
    {
        $data = $this->input->post();

        $this->db->trans_start();
        $id_colis = $data["id_colis"];
        $idtfi = $data["idtfi"];
        if (isset($data["commentaire"]))
            $commentaire = $data["commentaire"];

        $colis_temp = $this->CommandeTFIModel->getColisById($id_colis);
        $data_colis["id_statutcolis"] = RECEIVED_PACKAGE;
        $data_colis["date_reception"] = date('Y-m-d H:i:s');
        if (isset($commentaire))
            $data_colis["comment_reception"] = $commentaire;
        $this->CommandeTFIModel->changeStatusColis($id_colis, $data_colis);

        $liste_colis_produit = $data['productsToAdd'];

        foreach ($liste_colis_produit as $key => $colis) {
            $prod_tfi = $this->CommandeTFIModel->getProdTFI($colis_temp->id_cmd, $idtfi, $colis['id_produit'], $colis['reference']);

            if ($prod_tfi) {
                $data_update["stock_tfi"] = $prod_tfi->stock_tfi + $colis['quantite'];
                $stock_transit = $prod_tfi->stock_transit - $colis['ancien_quantite'];
                if ($stock_transit >= 0) {
                    $data_update["stock_transit"] = $stock_transit;
                }
                $this->CommandeTFIModel->updateStockTFI($prod_tfi->id, $data_update);
            } else {
                $data_insert["id_produit"] = $colis['id_produit'];
                $data_insert["id_user"] = $idtfi;
                $data_insert["id_cmd"] = $colis_temp->id_cmd;
                $data_insert["stock_tfi"] = $colis['quantite'];
                $this->CommandeTFIModel->insertStockTFI($data_insert, '+');
            }
            if ($prod_tfi->id_categorie == ID_PRODUIT_COUTEUX || $prod_tfi->id_categorie == ID_PRODUIT_EPISPE) {
                $data_rma_historique["id_user"] = $idtfi;
                $data_rma_historique["id_etat_validation_rma"] = EN_SERVICE;
                $data_rma_historique["date_creation"] = date('Y-m-d');
                $data_rma_historique["id_produit_tfi"] = $prod_tfi->id;
                $this->db->insert('rma_historique', $data_rma_historique);
                $data_update_produit_tfi["id_etat_rma"] = EN_SERVICE;
                $data_update_produit_tfi["id_etat_validation_rma"] = EN_SERVICE;
                $this->CommandeTFIModel->updateStockTFI($prod_tfi->id, $data_update_produit_tfi);
            }
            if ($colis['reference'] != null && !empty($colis['reference']))
                $this->CommandeTFIModel->updateElementColis(array('quantite_recue' => $colis['quantite']), array('id_colis' => $id_colis, 'id_produit' => $colis['id_produit'], 'reference' => $colis['reference']));
            else
                $this->CommandeTFIModel->updateElementColis(array('quantite_recue' => $colis['quantite']), array('id_colis' => $id_colis, 'id_produit' => $colis['id_produit']));
        }


        $id_cmd = $this->CommandeTFIModel->getCmdByColis($id_colis)->id_cmd;
        $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($id_cmd);
        $qte_total_colis = 0;
        $qte_total_cmd = 0;

        foreach ($liste_commande_produit as $key => $cmd) {
            $qte_total_colis += $this->CommandeTFIModel->getQteColisByProd($id_cmd, $cmd->id_produit);
            $qte_total_cmd += $this->CommandeTFIModel->getQteCmdByProd($id_cmd, $cmd->id_produit);
        }

        $shipped_colis = $this->CommandeTFIModel->getLivredColisByCmd($id_cmd);
        if ($shipped_colis == 0 && $qte_total_cmd == $qte_total_colis) {
            $data_statut["id_cmd"] = $id_cmd;
            $data_statut["id_statcmd"] = RECEIVED;
            $data_statut["cree_par"] = $this->user->getUser();
            $data_statut["date_creation"] = date('Y-m-d H:i:s');
            $this->CommandeTFIModel->changeStatus($data_statut);
        }

        echo json_encode(['erreur' => '0', 'reponse' => 'Le Colis à été Recus avec succès !!', 'id_colis' => $colis_temp->id_cmd]);
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $message = "";
            $message .= $this->infoCommande($id_cmd);
            $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
            $message .= "<b>Reçue par : </b>" . $cree_par->prenom . " " . $cree_par->nom . "<br><br>";
            if (!empty($data["commentaire"])) {
                $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
            }
            $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité expédiée</th><th style='" . style_td_th . "' align=\"center\">Quantité reçue</th></tr>";
            foreach ($liste_colis_produit as $key => $colis) {
                $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $this->ProduitModel->getInfoProduit($colis['id_produit'])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($colis['id_produit'])->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . $colis['ancien_quantite'] . "</td><td style='" . style_td_th . "' align=\"center\">" . $colis['quantite'] . "</td></tr>";
            }
            $table .= "</table><br>";
            if (count($liste_colis_produit) > 0) {
                $message .= $table;
            }
            $destinataire = $this->getDestinataire($id_cmd);
            $reference = $this->CommandeTFIModel->getInfoCommande($id_cmd)->reference;
            $object = "[SLAM] - Colis reçu acquitté " . $reference . " - " . date('d/m/Y');
            $tabCopy = array();
            if ($idtfi != $this->user->getUser()) {
                $this->SendMail($destinataire, $message, $object, $tabCopy);
            }
        }

    }

    public function demosend()
    {
        $tabCopy = array();
        $this->sendTo('ayoubamm@gmail.com', 'teste', 'teste', $tabCopy);
    }


    public function updateproduit($id_produit_anncien, $id_produit_nouv)
    {
        echo "------------------------- table commande produit id produit = " . $id_produit_anncien . "----------------------------<pre>";
        var_dump($this->CommandeTFIModel->mise_a_jour($id_produit_anncien));
        echo "-----------------------------------------------------</pre>";
        echo "<br><br>";

        echo "------------------------- table commande produit  id produit = " . $id_produit_nouv . " ----------------------------<pre>";
        var_dump($this->CommandeTFIModel->mise_a_jour($id_produit_nouv));
        echo "-----------------------------------------------------</pre>";
        echo "<br><br>";

        echo "-------------------------- table commande_produit refuse id produit = " . $id_produit_anncien . " ---------------------------<pre>";
        var_dump($this->CommandeTFIModel->mise_a_jour_v1($id_produit_anncien));
        echo "------------------------------------------------------</pre>";
        echo "<br><br>";

        echo "-------------------------- table commande_produit refuse id produit = " . $id_produit_nouv . " ---------------------------<pre>";
        var_dump($this->CommandeTFIModel->mise_a_jour_v1($id_produit_nouv));
        echo "------------------------------------------------------</pre>";
        echo "<br><br>";


    }

    public function myupdate($id_produit_anncien, $id_produit_nouv)
    {

        $this->db->trans_begin();
        $produit_anncien = $this->ProduitModel->getProduitBydesignation($id_produit_anncien);
        $produit_nouv = $this->ProduitModel->getProduitBydesignation($id_produit_nouv);
        $produit_stock_arg_nouv = $produit_anncien->stock_arg;
        $produit_stock_publi_nouv = $produit_anncien->stock_publi;
        if ($produit_anncien && $produit_nouv) {
            $this->ProduitModel->modifier($produit_nouv->id_produit, array('stock_arg' => $produit_nouv->stock_arg + $produit_stock_arg_nouv, 'stock_publi' => $produit_nouv->stock_publi + $produit_stock_publi_nouv));
            $this->ProduitModel->modifier($produit_anncien->id_produit, array('stock_arg' => 0, 'stock_publi' => 0));
            $data = array('id_produit' => $produit_anncien->id_produit);
            $liste_produit_anncien = $this->ProduitModel->getStockClrParProduit($data);
            foreach ($liste_produit_anncien as $stock_clr_ancien) {
                $search = array('id_adresses_clr' => $stock_clr_ancien->id_adresses_clr, 'id_produit' => $produit_nouv->id_produit);
                $result = $this->ProduitModel->findStockClr($search);
                if ($result != Null) {
                    $update = array(
                        'quantite' => ($stock_clr_ancien->quantite + $result->quantite),
                    );
                    $this->StockCLRModel->modifierStockProduit($update, $result->id);
                } else {
                    $insert = array(
                        'id_produit' => $produit_nouv->id_produit,
                        'id_adresses_clr' => $stock_clr_ancien->id_adresses_clr,
                        'quantite' => $stock_clr_ancien->quantite,
                    );
                    $this->StockCLRModel->ajouterStockProduit($insert);
                }
                $this->StockCLRModel->modifierStockProduit(array('quantite' => 0), $stock_clr_ancien->id);
            }
            $this->CommandeTFIModel->updateproduits($produit_anncien->id_produit, $produit_nouv->id_produit);
        } else {
            echo "erreur de selection designation de produit";
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
        }
    }

    public function annuler()
    {
        $data = $this->input->post();
        $this->db->trans_begin();
        $idtfi = $data["idtfi"];
        $data["id_statcmd"] = CANCELED;
        $data["cree_par"] = $this->user->getUser();
        $data["date_creation"] = date('Y-m-d H:i:s');
        unset($data["idtfi"]);
        $this->CommandeTFIModel->changeStatus($data);
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $message = "";
            $message .= $this->infoCommande($data["id_cmd"]);
            $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
            $message .= "<b> Annulée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . "<br><br>";
            if (!empty($data["commentaire"])) {
                $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
            }
            $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($data["id_cmd"]);
            foreach ($liste_commande_produit as $produit) {
                $message .= "quantite commandées : " . $produit->quantite . " , quantite validées  : 0 ,  quantite annulées : " . $produit->quantite . " " . $produit->designation . " (" . $produit->reference_free . ") <br>";
            }

        }
        redirect(site_url('CommandeTFI/detail/' . $data['id_cmd']));
    }

    public function actualiserCommandesVersExp()
    {
        $id_commandes_preparees = $this->CommandeTFIModel->getAllPreparedCommend();

        $ups = new UPS();
        foreach ($id_commandes_preparees as $id_commande) {
            $commande_colis = $this->CommandeTFIModel->getColisByCmd($id_commande->id_cmd);
            $shipped_colis_count = 0;
            $delived_colis_count = 0;
            foreach ($commande_colis as $colis) {
                if ($colis->id_statutcolis == COLIS_PREPARER) {
                    $ups_tracking_response = $ups->getTraking($colis->tracking_ups);
                    if ($ups->packageISReadyForUps($ups_tracking_response)) {
                        $this->CommandeTFIModel->changeStatusColis($colis->id_colis, ['id_statutcolis' => SHIPPED_PACKAGE]);
                        $shipped_colis_count++;
                    } elseif ($ups->packageISDelivered($ups_tracking_response)) {
                        $this->CommandeTFIModel->changeStatusColis($colis->id_colis, ['id_statutcolis' => DELIVERED_PACKAGE]);
                        $delived_colis_count++;
                    }
                } else if ($colis->id_statutcolis == SHIPPED_PACKAGE) {
                    $ups_tracking_response = $ups->getTraking($colis->tracking_ups);
                    if ($ups->packageISDelivered($ups_tracking_response)) {
                        $this->CommandeTFIModel->changeStatusColis($colis->id_colis, ['id_statutcolis' => DELIVERED_PACKAGE]);
                        $delived_colis_count++;
                    }
                } else if ($colis->id_statutcolis == DELIVERED_PACKAGE) {
                    $delived_colis_count++;
                }
            }
            $statut_commande = CMD_PREPARER;
            if ($shipped_colis_count == count($commande_colis) && count($commande_colis) > 0) {
                $stat_commande = SHIPPED;
            } elseif ($delived_colis_count == count($commande_colis) && count($commande_colis) > 0) {
                $stat_commande = LIVRE;
            }
            if ($statut_commande != CMD_PREPARER) {
                $data_statut = array();
                $data_statut["id_cmd"] = $id_commande->id_cmd;
                $data_statut["id_statcmd"] = $statut_commande;
                $data_statut["cree_par"] = $this->user->getUser();
                $data_statut["date_creation"] = date('Y-m-d H:i:s');
                $this->CommandeTFIModel->changeStatus($data_statut);
            }
            $this->CommandeTFIModel->updateCommande($id_commande->id_cmd);
        }
    }

    public function actualiserCommandesVersLivree()
    {

        $id_commandes_expediees = $this->CommandeTFIModel->getAllShippedCommend();
        $ups = new UPS();
        foreach ($id_commandes_expediees as $id_commande) {

            $commande_colis = $this->CommandeTFIModel->getColisByCmd($id_commande->id_cmd);
            $shipped_colis_count = 0;
            foreach ($commande_colis as $colis) {
                if ($colis->id_statutcolis == SHIPPED_PACKAGE) {
                    $ups_tracking_response = $ups->getTraking($colis->tracking_ups);
                    if ($ups->packageISDelivered($ups_tracking_response)) {
                        $this->CommandeTFIModel->changeStatusColis($colis->id_colis, ['id_statutcolis' => DELIVERED_PACKAGE]);
                        $shipped_colis_count++;
                    }
                } else if ($colis->id_statutcolis == DELIVERED_PACKAGE) {
                    $shipped_colis_count++;
                }
            }

            if ($shipped_colis_count == count($commande_colis) && count($commande_colis) > 0) {
                $data_statut = array();
                $data_statut["id_cmd"] = $id_commande->id_cmd;
                $data_statut["id_statcmd"] = LIVRE;
                $data_statut["cree_par"] = $this->user->getUser();
                $data_statut["date_creation"] = date('Y-m-d H:i:s');
                $this->CommandeTFIModel->changeStatus($data_statut);
            }
            $this->CommandeTFIModel->updateCommande($id_commande->id_cmd);
        }
    }

    public function actualiserCommandesVersLivreeWithDateLevee()
    {

        $id_commandes_expediees = $this->CommandeTFIModel->getAllShippedCommend();
        $ups = new UPS();

        foreach ($id_commandes_expediees as $id_commande) {

            $commande_colis = $this->CommandeTFIModel->getColisByCmd($id_commande->id_cmd);
            $shipped_colis_count = 0;
            foreach ($commande_colis as $colis) {
                $ups_tracking_response = $ups->getTraking($colis->tracking_ups);
                if ($ups->getDatepackageISDelivered($ups_tracking_response)) {
                    $time = strtotime($ups_tracking_response["results"]["TrackResponse"]["Shipment"]["Package"]["Activity"]["Date"] . "" . $ups_tracking_response["results"]["TrackResponse"]["Shipment"]["Package"]["Activity"]["Time"]);
                    $newformat = date('Y-m-d h:m:s', $time);
                    $this->CommandeTFIModel->changeStatusColis($colis->id_colis, ['date_levee' => $newformat]);
                }
            }
            $this->CommandeTFIModel->updateCommande($id_commande->id_cmd);
        }
    }


    /**
     * Methode qui permet supprimer tous les commande
     */
    public function supprimerTousCMD()
    {
        echo $this->CommandeTFIModel->supprimerTous() . " CommandeTFI supprimées !!";
    }

    /**
     * getTraking 1Z43149X6898888138
     */
    public function getTraking($codeTracking, $id_commande)
    {

        $ups_array = array(
            'UPSSecurity' => array(
                'UsernameToken' => array(
                    'Username' => USERNAME,
                    'Password' => PASSWORD
                ),
                'ServiceAccessToken' => array(
                    'AccessLicenseNumber' => ACCES_LICENSE_NUMBRE
                )
            ),
            'TrackRequest' => array(
                'Request' => array(
                    'RequestOption' => 1,
                    'TransactionReference' => array(
                        'CustomerContext' => 'Test 001'
                    )
                ),
                'InquiryNumber' => $codeTracking //'1ZE1XXXXXXXXXXXXXX'
            )
        );

        $params = json_encode($ups_array);

        $results_json = $this->setParamttre(URL_UPS_TRACKING_TEST, $params);

        $results = json_decode($results_json, true);
        $data = $this->results_shipping($results);
        print "<PRE><FONT COLOR=RED>";
        print_r($results);
        print "</FONT></PRE>";
        die();
        $this->load->view('CommandeTFI/tracking',
            [
                "data" => $data,
                "id_commande" => $id_commande
            ]
        );


    }

    /**
     * function de Curl Tracking
     */
    public function setParamttre($url, $params)
    {

        $headers = array();
        $headers[] = 'Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept';
        $headers[] = 'Access-Control-Allow-Methods: POST';
        $headers[] = 'Access-Control-Allow-Origin: *';
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 100);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $response = curl_exec($ch);

        if ($response === false) {
            echo curl_error($ch);
        }

        return $response;
    }

    public function AnnulerCommande()
    {
        $data = $this->input->post();
        $this->db->trans_begin();
        $this->CommandeTFIModel->AnnulerCommande($data);
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $message = "";
            $message .= $this->infoCommande($data["id_cmd"]);
            $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
            $message .= "<b> Annulée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . " <br><br>";
            if (!empty($data["commentaire"])) {
                $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
            }
            $table = "<table border=\"1\" width='100%' style='" . style_table . "'><tr style='" . tr . "'><th align=\"left\" style='" . style_td_th_produit . "'>Produit</th><th align=\"center\" style='" . style_td_th . "'>Quantité commandée</th><th align=\"center\" style='" . style_td_th . "'>Quantité validée</th><th style='" . style_td_th . "' align=\"center\">Quantité annulée</th></tr>";
            $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($data["id_cmd"]);
            foreach ($liste_commande_produit as $produit) {
                if ($produit->id_categorie == ID_PRODUIT_COUTEUX) {
                    for ($i = 0; $i < $produit->quantite; $i++) {
                        $table .= "<tr><td style='" . style_td_th_produit . "' align=\"left\">" . $produit->designation . " (<i>" . $produit->reference_free . "</i>)" . "</td><td align=\"center\" style='" . style_td_th . "' >1</td><td align=\"center\">0</td><td style='" . style_td_th . "' align=\"center\">1</td></tr>";
                    }
                } elseif ($produit->id_categorie == ID_PRODUIT_COUTEUX) {
                    for ($i = 0; $i < $produit->quantite; $i++) {
                        $table .= "<tr><td style='" . style_td_th_produit . "' align=\"left\">" . $produit->designation . " (<i>" . $produit->reference_free . "</i>)" . "</td><td align=\"center\" style='" . style_td_th . "' >1</td><td align=\"center\">0</td><td style='" . style_td_th . "' align=\"center\">1</td></tr>";
                    }
                } else {
                    $table .= "<tr><td align=\"left\">" . $produit->designation . " (<i>" . $produit->reference_free . "</i>)" . "</td><td align=\"center\">" . $produit->quantite . "</td><td align=\"center\">0</td><td align=\"center\">" . $produit->quantite . "</td></tr>";
                }
            }
            $table .= "</table><br>";
            if (count($liste_commande_produit) > 0) {
                $message .= $table;
            }
            $destinataire = $this->getDestinataire($data["id_cmd"]);
            $reference = $this->CommandeTFIModel->getInfoCommande($data["id_cmd"])->reference;
            $object = "[SLAM] Refus de commande : " . $reference . " - " . date('d/m/Y');
            $tabCopy = array();

            $this->SendMail($destinataire, $message, $object, $tabCopy);
        }
        $this->session->set_flashdata("annulation", "Cette commande est annulé");
        redirect('CommandeTFI/detail/' . $data["id_cmd"]);
    }
    public function Repassercommande()
    {
        $data = $this->input->post();
        $this->db->trans_begin();
        $this->CommandeTFIModel->Repassercommande($data);
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
        }
        $this->session->set_flashdata("annulation", "Cette commande est Repasser en attente de validation");
        redirect('CommandeTFI/detail/' . $data["id_cmd"]);
    }

    public function GenerateCSV($id_state)
    {
        if (has_permission(ARG_PROFILE))
            $data = $this->CommandeTFIModel->liste_csv(WAIT_VALIDATION_AGR);
        if (has_permission(CLR_PROFILE))
            $data = $this->CommandeTFIModel->liste_csv(WAIT_VALIDATION_AGR);
        $i = 0;
        foreach ($data as $cmd) {
            $data[$i]->date_valid = $this->CommandeTFIModel->date_valid($cmd->id_cmd);
            $data[$i]->produits = $this->CommandeTFIModel->liste_produitbycmd($cmd->id_cmd);

            $i++;
        }
        $fp = realpath("./public/csv_file");
        $file = fopen($fp . '/file.csv', 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, array('Ref. Commande', "Destinataire", "Désignation produit", "Ref FREE", "Quantité restante à expédier", 'Livraison', "Date de Validation de la commande", "Retard"), ";");

        foreach ($data as $cmd) {
            foreach ($cmd->produits as $p) {
                if (($p->quantite - (($p->quantite_expedie) ? $p->quantite_expedie : 0)) > 0) {
                    $date_array = explode(" ", $cmd->date_valid);
                    $date_valid = date("Y-m-d", strtotime($cmd->date_valid));
                    $datetime1 = new DateTime($date_valid);

                    $datetime2 = new DateTime(date("Y-m-d"));
                    $interval = $datetime1->diff($datetime2);
                    $retartd = $interval->format('%a');;
                    $date = explode("-", $date_array[0]);
                    $str = '"' . $cmd->reference . '";"' . $cmd->nom . ' ' . $cmd->prenom . '";"' . $p->designation . '";"' . $p->reference_free . '";"' . $p->quantite . '";"' . (($p->quantite_expedie) ? $p->quantite_expedie : 0) . '";"' . ($p->quantite - (($p->quantite_expedie) ? $p->quantite_expedie : 0)) . '";"' . $cmd->type_livree . '";"' . $date[2] . '/' . $date[1] . '/' . $date[0] . '"';
                    $array = array($cmd->reference, $cmd->nom . ' ' . $cmd->prenom, $p->designation, $p->reference_free, ($p->quantite - (($p->quantite_expedie) ? $p->quantite_expedie : 0)), $cmd->type_livree, $date[2] . '/' . $date[1] . '/' . $date[0], $retartd);
                    fputcsv($file, $array, ";");
                }
            }

        }


        fclose($file);
        echo json_encode(true);
    }


    public function enregistrer_pack($id, $idtfi, $logistique = null)
    {
        $id_pack = $id;
        $check = false;
        $this->db->where("id_pack", $id);
        $query = $this->db->get("pack_produit");
        $produits = $query->result();
        $array = array();
        $i = 0;
        foreach ($produits as $key => $value) {
            $array[$i] = array("id_produit" => $value->id_produit, "quantite" => $value->quantite);
            $i++;
        }
        $tailles = unserialize(TAILLES_POLO_REF);
        if (isset($array))
            $productsToAdd = $array;
        else
            $productsToAdd = [];

        foreach ($productsToAdd as $key => $prod) {
            if ($prod["quantite"] <= 0)
                unset($productsToAdd[$key]);
        }
        if (count($productsToAdd) > 0) {
            $data_cmd["cree_par"] = $this->user->getUser();
            $data_cmd["date_creation"] = date('Y-m-d H:i:s');
            $data_cmd["id_user"] = $idtfi;

            foreach ($productsToAdd as $key => $p) {
                if ($this->CommandeTFIModel->has_taille($p["id_produit"])) {
                    if (count($this->CommandeTFIModel->has_taille($p["id_produit"])) > 0) {
                        $taille_user = $this->CommandeTFIModel->get_taille($p["id_produit"], $idtfi);
                        $t = strtolower($this->CommandeTFIModel->has_taille($p["id_produit"]));
                        $taille_user_vet = $taille_user->$t;
                        if (is_numeric($taille_user_vet)) {

                            $id = $this->CommandeTFIModel->get_idbyref($p["id_produit"], $taille_user_vet);
                            if ($id) {
                                $productsToAdd[$key]["id_produit"] = $id;
                            } else {
                                $check = true;
                            }
                        } else {
                            if (isset($tailles[0][$taille_user_vet])) {
                                $id = $this->CommandeTFIModel->get_idbyref($p["id_produit"], $tailles[0][$taille_user_vet]);
                                if ($id) {
                                    $productsToAdd[$key]["id_produit"] = $id;
                                } else {
                                    $check = true;
                                }
                            } else {
                                $check = true;
                            }

                        }
                    }
                }
            }
        }
        if (!$check) {
            $data = array();
            foreach ($productsToAdd as $key => $value) {
                if (isset($data['qte'][$value["id_produit"]])) {
                    $data['qte'][$value["id_produit"]][0] = $data['qte'][$value["id_produit"]][0] + $value["quantite"];
                } else {
                    $data['qte'][$value["id_produit"]] = $value["quantite"];
                }
            }
            if ($data == null) {
                redirect(site_url('CommandeTFI/ajouter/' . $idtfi));
            }
            $usertfi = $this->UtilisateurModel->checkUserById($idtfi);
            $depot_ups = $this->DepotModel->getIdDepotByUser($usertfi->id_user);
            $depot_clr = $this->UserAdressesModel->getAdresseCLRUser($usertfi->id_user);
            $liste_produits = [];
            if (isset($usertfi->id_post) && $usertfi->id_post != null)
                $liste_produits = $this->CommandeTFIModel->listeProduitsByTFI($idtfi);
            else
                $liste_produits = $this->CommandeTFIModel->listeProduitsByCDT();


            if ($usertfi->designation == 'TFI' && !has_permission(TFI_PROFILE))
                $mes_commandes_cdt = false;
            else if ($idtfi != $this->user->getUser() && has_permission(CDP_PROFILE))
                $mes_commandes_cdt = false;
            else
                $mes_commandes_cdt = true;

            foreach ($liste_produits as $key => $prod) {
                $produit = $this->CommandeTFIModel->getStockTFIByCommande($idtfi, $prod->id_produit);
                if ($produit) {
                    $liste_produits[$key]->stock_tfi = $produit->stock_tfi;
                    $liste_produits[$key]->stock_transit = $produit->stock_transit;
                } else {
                    $liste_produits[$key]->stock_tfi = 0;
                    $liste_produits[$key]->stock_transit = 0;
                }
            }
            $this->load->view('CommandeTFI/detail_commande',
                [
                    "usertfi" => $usertfi,
                    "liste_produits" => $liste_produits,
                    "liste_qte" => $data,
                    "depot_ups" => $depot_ups,
                    "depot_clr" => $depot_clr,
                    "mes_commandes_cdt" => $mes_commandes_cdt,
                    "pack" => $id_pack,
                    "logistique" => null
                ]
            );
        } else {
            $this->session->set_flashdata("pack_error", "Vous ne pouvez pas commander le pack");
            redirect(site_url('CommandeTFI/ajouter/' . $idtfi));
        }


    }

    public function list_ajax_sla()
    {

        $time_start = microtime(true);
        $data = $this->input->post();

        $userId = $this->input->post("userid");
        $categorie_id = $this->input->post("id_cat");
        $id_statut = $this->input->post("id_statut");
        if ($id_statut == RELIQUAT_SLA) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => 'h1.date_creation',
                4 => "id_upr.nom",
                5 => "cdt.nom",
                6 => "tfi.nom",
                7 => "s.libelle",
                8 => "bar",
                9 => ""
            );
        } else if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == LIVRE) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "id_upr.nom",
                5 => "tfi.nom",
                6 => "h1.date_creation",
                7 => "u1.nom",
                8 => "h1.commentaire",
                9 => "s.libelle"
            );
        } else if ($id_statut == RECEIVED || $id_statut == SHIPPED) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "id_upr.nom",
                5 => "tfi.nom",
                6 => "h1.date_creation",
                7 => "u1.nom",
                8 => "h1.commentaire",
                9 => "s.libelle"
            );
        } else if ($id_statut == WAIT_VALIDATION) {
            $columns = array(0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "id_upr.nom",
                5 => "tfi.nom",
                6 => "s.libelle",
                7 => "bar",
                8 => ""
            );
        } else {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "id_upr.nom",
                5 => "tfi.nom",
                6 => "s.libelle"
            );
        }
        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $order = $columns[$this->input->post('order')[0]['column']];
        $dir = $this->input->post('order')[0]['dir'];
        $filtre1 = substr($this->input->post('columns')[3]['search']['value'], 1, -1);
        $filtre2 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
        if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == LIVRE) {
            $filtre3 = substr($this->input->post('columns')[8]['search']['value'], 1, -1);
        } else if ($id_statut == RELIQUAT_SLA) {
            $filtre1 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
            $filtre2 = substr($this->input->post('columns')[5]['search']['value'], 1, -1);
            $filtre3 = substr($this->input->post('columns')[7]['search']['value'], 1, -1);
        } else if ($id_statut == RECEIVED || $id_statut == SHIPPED) {
            $filtre3 = substr($this->input->post('columns')[7]['search']['value'], 1, -1);
        } else
            $filtre3 = substr($this->input->post('columns')[5]['search']['value'], 1, -1);

        $totalData = $this->CommandeTFIModel->allslacmd_count(null, $id_statut, $filtre1, $filtre2, $filtre3);

        $totalFiltered = $totalData;

        if (empty($this->input->post('search')['value']) && empty($categorie_id)) {
            $posts = $this->CommandeTFIModel->allslacmd($limit, $start, $order, $dir, null, $id_statut, $filtre1, $filtre2, $filtre3);


        } else {
            $search = $this->input->post('search')['value'];

            $posts = $this->CommandeTFIModel->allslacmd($limit, $start, $order, $dir, null, $id_statut, $filtre1, $filtre2, $filtre3, $search, $categorie_id);

            $totalFiltered = $this->CommandeTFIModel->allslacmd_count(null, $id_statut, $filtre1, $filtre2, $filtre3, $search, $categorie_id);
        }


        $data = array();
        if (!empty($posts)) {
            //var_dump($posts);
            foreach ($posts as $post) {
                $nestedData['id'] = $post->id;
                $nestedData['reference'] = $post->reference;
                $nestedData['created'] = date('d/m/Y', strtotime($post->date_creation));
                $nestedData['created_by'] = $post->nom_cdt
                    . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $post->nom_cdt_sup;
                $nestedData['destinataire'] = $post->nom_tfi
                    . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $post->nom_tfi_sup;
                $nestedData['etat'] = $post->etat_cmd;
                $nestedData['upr'] = (($post->nom_upr) ? $post->nom_upr : "---");
                $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'>
                                                    <i class=\"fa fa-eye\"></i></a>";


                if ($id_statut == RELIQUAT_SLA) {
                    $nestedData['reliquat'] = date('d/m/Y', strtotime($post->date_creation));


                }
                if ($id_statut == RELIQUAT_SLA || $id_statut == WAIT_VALIDATION) {
                    if (($post->bar * 100) <= 25) {
                        $nestedData['stock_ordre'] = $post->bar * 100;
                        $nestedData['stock_dispo'] = '<div class="progress">
                                              <div class="progress-bar progress-bar-danger" role="progressbar" aria-valuenow=".round(($dispo/$k)*100)." aria-valuemax="100" style="width:' . round($post->bar * 100) . '%"> <span class="bartext">' . round($post->bar * 100) . '%</span>
                                              </div>
                                              </div> ';
                    } else if (($post->bar * 100) > 25 && ($post->bar * 100) <= 50) {
                        $nestedData['stock_ordre'] = $post->bar * 100;
                        $nestedData['stock_dispo'] = '<div class="progress">
                                          <div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow=".round(($dispo/$k)*100)." aria-valuemax="100" style="width:' . round($post->bar * 100) . '%"><span class="bartext">' . round($post->bar * 100) . '%</span>
                                          </div>
                                          </div> ';
                    } else if (($post->bar * 100) > 50 && ($post->bar * 100) <= 75) {
                        $nestedData['stock_ordre'] = $post->bar * 100;
                        $nestedData['stock_dispo'] = '<div class="progress" >
                                        <div class="progress-bar "  role="progressbar" aria-valuenow=".round($dispo)." aria-valuemax="100" style="width:' . round($post->bar * 100) . '%;background:  #FFEB3B;"><span class="bartext">' . round($post->bar * 100) . '%</span>
                                        </div>
                                        </div> ';
                    } else if (($post->bar * 100) > 75) {
                        $nestedData['stock_ordre'] = $post->bar * 100;
                        $nestedData['stock_dispo'] = '<div class="progress">
                                        <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow=".round($dispo)." aria-valuemax="100" style="width:' . round($post->bar * 100) . '%"> <span class="bartext">' . round($post->bar * 100) . '%</span>
                                        </div>
                                        </div> ';
                    }

                }
                if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == RECEIVED || $id_statut == SHIPPED || $id_statut == LIVRE) {
                    $nestedData['validation'] = date('d/m/Y', strtotime($post->date_validation));
                    $nestedData['validee_par'] = $post->nom_validation . " " . $post->prenom_validation;
                    $nestedData['remarque'] = $post->commentaire_validation;
                }
                if ($id_statut == RECEIVED || $id_statut == SHIPPED) {
                    $post->colis = $this->CommandeTFIModel->getTotalColisByCmd($post->id);
                    $post->colis_recus = $this->CommandeTFIModel->getTotalColisRecusByCmd($post->id);
                    $nestedData['nbr_colis'] = $post->colis_recus->total_recus . "/" . $post->colis->total;
                }
                $data[] = $nestedData;

            }
        }

        $time_stop = microtime(true) - $time_start;

        $json_data = array(
            "draw" => intval($this->input->post('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data,
            "time" => number_format($time_stop,2,".","").' sec'
        );

        echo json_encode($json_data);

    }

    public function liste_ajax_clr()
    {
        $time_start = microtime(true);
        $data = $this->input->post();
        $id_statut = $this->input->post('id_statut');
        $id_user = $this->user->getUser();
        $filtre1 = substr($this->input->post('columns')[2]['search']['value'], 1, -1);
        $filtre2 = substr($this->input->post('columns')[3]['search']['value'], 1, -1);
        $filtre3 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
        if ($id_statut == WAIT_VALIDATION_AGR || !$id_statut) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => "cdt.nom",
                3 => "tfi.nom",
                4 => "s.libelle",
                5 => "",
                6 => "",
            );
        } elseif ($id_statut == CMD_PREPARER) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => "cdt.nom",
                3 => "tfi.nom",
                4 => "h1.date_creation",
                5 => "u1.nom",
                6 => "s.libelle",
            );
        } elseif ($id_statut == SHIPPED) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => "cdt.nom",
                3 => "tfi.nom",
                4 => "h1.date_creation",
                5 => "u1.nom",
                6 => "h2.date_creation",
                7 => "s.libelle",
                8 => "",
            );
        } elseif ($id_statut == LIVRE) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => "cdt.nom",
                3 => "tfi.nom",
                4 => "h1.date_creation",
                5 => "u.nom",
                6 => "h2.date_creation",
                7 => "s.libelle",
            );
        } elseif ($id_statut == RECEIVED) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => "cdt.nom",
                3 => "tfi.nom",
                4 => "h1.date_creation",
                5 => "u.nom",
                6 => "h2.date_creation",
                7 => "h.date_creation",
                8 => "s.libelle",
                9 => "",

            );
        }
        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $order = $columns[$this->input->post('order')[0]['column']];
        $dir = $this->input->post('order')[0]['dir'];
        $totalData = $this->CommandeTFIModel->all_clr_count(null, $id_statut, $filtre1, $filtre2, $filtre3);
        $totalFiltered = $totalData;
        $posts = $this->CommandeTFIModel->allclrcmd($limit, $start, $order, $dir, null, $id_statut, $filtre1, $filtre2, $filtre3);

        if (empty($this->input->post('search')['value'])) {
            $posts = $this->CommandeTFIModel->allclrcmd($limit, $start, $order, $dir, null, $id_statut, $filtre1, $filtre2, $filtre3);
        } else {
            $search = $this->input->post('search')['value'];

            $posts = $this->CommandeTFIModel->allclrcmd($limit, $start, $order, $dir, null, $id_statut, $filtre1, $filtre2, $filtre3, $search);

            $totalFiltered = $this->CommandeTFIModel->all_clr_count(null, $id_statut, $filtre1, $filtre2, $filtre3, $search);
        }
        $data = array();
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $nestedData['id'] = $post->id;
                $nestedData['reference'] = $post->reference;
                $nestedData['creee_par'] = $post->nom_cdt . ' ' . $post->prenom_cdt .
                    "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $post->nom_cdt_sup;
                $nestedData['date_creation'] = date('d/m/Y', strtotime($post->date_creation));
                $nestedData['destinataire'] = $post->nom_tfi . ' ' . $post->prenom_tfi
                    . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $post->nom_tfi_sup;
                $nestedData['etat'] = $post->etat_cmd;
                if ($id_statut == WAIT_VALIDATION_AGR && has_permission(CLR_PROFILE)) {
                    $i = 0;
                    $j = 0;
                    foreach ($this->CommandeTFIModel->listeCommandeProduit($post->id) as $produit) {
                        $dispo = (($produit->stock_clr - $produit->stock_virtuel_clr) - ($produit->quantite - ((!$produit->quantite_expediee) ? 0 : $produit->quantite_expediee)));
                        if ($dispo < 0) {
                            $i++;
                        }
                        if ($dispo > 0) {
                            $j++;
                        }

                    }
                    if ($i > 0 && $j == 0)
                        $nestedData['stock_dispo'] = "<i class='fa fa-times-circle'></i>";
                    else if ($i > 0 && $j > 0)
                        $nestedData['stock_dispo'] = "<i class='fa fa-exclamation-triangle'></i>";
                    else if ($i == 0 && $j > 0)
                        $nestedData['stock_dispo'] = "<i class='fa fa-check-circle'></i>";

                }
                $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'>
                                                    <i class=\"fa fa-eye\"></i></a>";

                if ($id_statut == RELIQUAT_SLA) {
                    $nestedData['reliquat'] = date('d/m/Y', strtotime($post->date_reliquat));
                }
                if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == RECEIVED || $id_statut == SHIPPED || $id_statut == LIVRE) {
                    $nestedData['date_validation'] = date('d/m/Y', strtotime($post->date_validation));
                    $nestedData['valider_par'] = $post->nom_validation . " " . $post->prenom_validation;
                }
                if ($id_statut == RECEIVED || $id_statut == SHIPPED) {
                    $post->colis = $this->CommandeTFIModel->getTotalColisByCmd($post->id);
                    $post->colis_recus = $this->CommandeTFIModel->getTotalColisRecusByCmd($post->id);
                    $nestedData['date_expedition'] = ($post->date_expedition) ? date('d/m/Y', strtotime($post->date_expedition)) : '---';
                    $nestedData['date_reception'] = date('d/m/Y', strtotime($post->date_last_action));
                    $nestedData['nbr_colis'] = $post->colis_recus->total_recus . "/" . $post->colis->total;
                }
                if ($id_statut == LIVRE) {
                    $nestedData['date_expedition'] = ($post->date_expedition) ? date('d/m/Y', strtotime($post->date_expedition)) : '---';
                }
                $data[] = $nestedData;

            }
        }
        $time_stop = microtime(true) - $time_start;
        $json_data = array(
            "draw" => intval($this->input->post('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data,
            "time" => number_format($time_stop,2,".","").' sec'
        );

        echo json_encode($json_data);


    }

    public function list_ajax_tfi()
    {
        $time_start = microtime(true);

        $data = $this->input->post();
        $userId = $this->input->post("userid");
        $id_statut = $this->input->post("id_statut");
        if (has_permission(CDT_PROFILE) || has_permission(CDP_PROFILE)) {
            if ($id_statut == REFUSED_CDT || $id_statut == REFUSED_CDP || $id_statut == REFUSED) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'h.date_creation',
                    4 => "u.nom",
                    5 => "h.commentaire",
                    6 => ""
                );
            } else if ($id_statut == RELIQUAT_SLA) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'h.date_creation',
                    4 => ''
                );
            } else if ($id_statut == WAIT_VALIDATION_CDP) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'cdt.nom',
                    4 => "tfi.nom",
                    5 => "s.libelle"
                );
            } else if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == MISSING_STOCK) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "s.libelle"
                );
            } else if ($id_statut == SHIPPED || $id_statut == LIVRE) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.date_creation",
                    7 => "s.libelle"
                );
            } else if ($id_statut == RECEIVED) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.date_creation",
                    7 => "u3.nom",
                    8 => "h3.date_creation",
                    9 => "h.date_creation",
                    10 => "s.libelle"
                );
            } else {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'cdt.nom',
                    4 => 'tfi.nom',
                    5 => "s.libelle"
                );
            }
        } else {
            if ($id_statut == RELIQUAT_SLA) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'h.date_creation',
                    4 => ''
                );
            } else if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == LIVRE) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "s.libelle"
                );
            } else if ($id_statut == RECEIVED || $id_statut == SHIPPED) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "cdt.nom",
                    4 => "tfi.nom",
                    5 => "h1.date_creation",
                    6 => "u1.nom",
                    7 => "h1.commentaire",
                    8 => "s.libelle"
                );
            } else {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "s.libelle"
                );
            }
        }

        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $a = $this->input->post('order')[0]['column'];
        $order = $columns[$this->input->post('order')[0]['column']];
        $dir = $this->input->post('order')[0]['dir'];
        if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == LIVRE) {
            $filtre1 = substr($this->input->post('columns')[6]['search']['value'], 1, -1);
        } else if ($id_statut == RELIQUAT_SLA) {
            $filtre1 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
        } else if ($id_statut == RECEIVED || $id_statut == SHIPPED) {
            $filtre1 = substr($this->input->post('columns')[6]['search']['value'], 1, -1);
        } else if (empty($id_statut)) {
            $filtre1 = $this->input->post('id_etat');
        } else
            $filtre1 = substr($this->input->post('columns')[3]['search']['value'], 1, -1);

        $totalData = $this->CommandeTFIModel->alltficmd_count($userId, $id_statut, $filtre1);

        $totalFiltered = $totalData;

        if (empty($this->input->post('search')['value'])) {
            $posts = $this->CommandeTFIModel->alltficmd($limit, $start, $order, $dir, $userId, $id_statut, $filtre1);
        } else {
            $search = $this->input->post('search')['value'];

            $posts = $this->CommandeTFIModel->alltficmd($limit, $start, $order, $dir, $userId, $id_statut, $filtre1, $search);

            $totalFiltered = $this->CommandeTFIModel->alltficmd_count($userId, $id_statut, $filtre1, $search);
        }

        $data = array();
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $nestedData['id'] = $post->id;
                $nestedData['reference'] = $post->reference;
                $nestedData['created_by'] = $post->nom_cdt . " " . $post->prenom_cdt
                    . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $post->nom_cdt_sup;
                $nestedData['created'] = date('d/m/Y', strtotime($post->date_creation));
                $nestedData['destinataire'] = $post->nom_tfi . " " . $post->prenom_tfi
                    . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $post->nom_tfi_sup;
                $nestedData['etat'] = $post->etat_cmd;
                $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'>
                                                    <i class=\"fa fa-eye\"></i></a>";

                if ($id_statut == RELIQUAT_SLA) {
                    $nestedData['reliquat'] = date('d/m/Y', strtotime($post->date_last_action));
                }
                if ($id_statut == REFUSED_CDT || $id_statut == REFUSED_CDP || $id_statut == REFUSED) {
                    $nestedData['commentaire_refused'] = $post->commentaire_last_action;
                    $nestedData['refused_by'] = $post->nom_last_action . " " . $post->prenom_last_action;
                    $nestedData['date_refus'] = date('d/m/Y', strtotime($post->date_last_action));
                }
                if ($id_statut == MISSING_STOCK || $id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == RECEIVED || $id_statut == SHIPPED || $id_statut == LIVRE) {
                    $nestedData['validation'] = date('d/m/Y', strtotime($post->date_validation));
                    $nestedData['validee_par'] = $post->nom_validation . " " . $post->prenom_validation;
                    $nestedData['remarque'] = $post->commentaire_validation;
                }
                if ($id_statut == RECEIVED || $id_statut == SHIPPED || $id_statut == LIVRE) {
                    $post->colis = $this->CommandeTFIModel->getTotalColisByCmd($post->id);
                    $post->colis_recus = $this->CommandeTFIModel->getTotalColisRecusByCmd($post->id);
                    $nestedData['expedition'] = (($post->date_expedition) ? date('d/m/Y', strtotime($post->date_expedition)) : '');
                    $nestedData['nbr_colis'] = $post->colis_recus->total_recus . "/" . $post->colis->total;
                }
                if ($id_statut == RECEIVED) {
                    $nestedData['aquitt_par'] = $post->nom_livre . " " . $post->prenom_livre;
                    $time = explode(" ", $post->date_livre)[1];
                    $time = explode(":", $time);
                    $time = $time[0] . ":" . $time[1];
                    $nestedData['aquitt_le'] = date_fr($post->date_livre) . " " . $time;
                    $nestedData['reception'] = date('d/m/Y', strtotime($post->date_last_action));
                }
                $data[] = $nestedData;

            }
        }

        $time_stop = microtime(true) - $time_start;

        $json_data = array(
            "draw" => intval($this->input->post('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data,
            "time" => number_format($time_stop,2,".","").' sec'
        );

        echo json_encode($json_data);

    }

    public function list_ajax_admin()
    {
        $time_start = microtime(true);
        $data = $this->input->post();
        $userId = $this->input->post("userid");
        $id_statut = $this->input->post("id_statut");

        if ($id_statut == RELIQUAT_SLA) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => 'h1.date_creation',
                4 => "cdt.nom",
                5 => "tfi.nom",
                6 => "s.libelle"
            );
        } else if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "tfi.nom",
                5 => "h1.date_creation",
                6 => "u1.nom",
                7 => "h1.commentaire",
                8 => "s.libelle"
            );
        } else if ($id_statut == SHIPPED) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "tfi.nom",
                5 => "h1.date_creation",
                6 => "u1.nom",
                7 => "h1.commentaire",
                8 => "h2.date_creation",
                9 => "s.libelle"

            );
        } else if ($id_statut == LIVRE) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "tfi.nom",
                5 => "h1.date_creation",
                6 => "u1.nom",
                7 => "h1.commentaire",
                8 => "h2.date_creation",
                9 => "s.libelle"

            );
        } else if ($id_statut == RECEIVED) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "tfi.nom",
                5 => "h1.date_creation",
                6 => "u1.nom",
                7 => "h1.commentaire",
                8 => "h2.date_creation",
                9 => "h.date_creation",
                10 => "s.libelle"

            );
        } else {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => 'tfi.nom',
                5 => 's.libelle',

            );
        }
        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $a = $this->input->post('order')[0]['column'];

        $order = $columns[$this->input->post('order')[0]['column']];
        $dir = $this->input->post('order')[0]['dir'];
        $filtre1 = substr($this->input->post('columns')[3]['search']['value'], 1, -1);
        $filtre2 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
        $filtre3 = substr($this->input->post('columns')[5]['search']['value'], 1, -1);
        $totalData = $this->CommandeTFIModel->alladmincmd_count(null, $id_statut, $filtre1, $filtre2, $filtre3);

        $totalFiltered = $totalData;

        if (empty($this->input->post('search')['value'])) {
            $posts = $this->CommandeTFIModel->alladmincmd($limit, $start, $order, $dir, null, $id_statut, $filtre1, $filtre2, $filtre3);
        } else {
            $search = $this->input->post('search')['value'];

            $posts = $this->CommandeTFIModel->alladmincmd($limit, $start, $order, $dir, null, $id_statut, $filtre1, $filtre2, $filtre3, $search);

            $totalFiltered = $this->CommandeTFIModel->alladmincmd_count(null, $id_statut, $filtre1, $filtre2, $filtre3, $search);
        }

        $data = array();
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $nestedData['id'] = $post->id;
                $nestedData['reference'] = $post->reference;
                $nestedData['created_by'] = $post->nom_cdt;
                $nestedData['created'] = date('d/m/Y', strtotime($post->date_creation));
                $nestedData['destinataire'] = $post->nom_tfi;
                $nestedData['etat'] = $post->etat_cmd;
                $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'>
                                                    <i class=\"fa fa-eye\"></i></a>";

                if ($id_statut == RELIQUAT_SLA) {
                    $nestedData['reliquat'] = date('d/m/Y', strtotime($post->date_reliquat));
                }

                if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == RECEIVED || $id_statut == SHIPPED || $id_statut == LIVRE) {
                    $nestedData['validation'] = date('d/m/Y', strtotime($post->date_validation));
                    $nestedData['validee_par'] = $post->nom_validation . " " . $post->prenom_validation;
                    $nestedData['remarque'] = $post->commentaire_validation;
                }
                if ($id_statut == RECEIVED || $id_statut == SHIPPED) {
                    $post->colis = $this->CommandeTFIModel->getTotalColisByCmd($post->id);
                    $post->colis_recus = $this->CommandeTFIModel->getTotalColisRecusByCmd($post->id);
                    $nestedData['nbr_colis'] = $post->colis_recus->total_recus . "/" . $post->colis->total;
                }
                if ($id_statut == SHIPPED || $id_statut == RECEIVED || $id_statut == LIVRE) {
                    $nestedData['date_expedition'] = date_fr($post->date_expedition);
                }
                if ($id_statut == RECEIVED) {
                    $nestedData['date_reception'] = date('d/m/Y', strtotime($post->date_last_action));
                }
                $data[] = $nestedData;

            }
        }

        $time_stop = microtime(true) - $time_start;

        $json_data = array(
            "draw" => intval($this->input->post('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data,
            "time" => number_format($time_stop,2,".","").' sec'
        );

        echo json_encode($json_data);
    }


    public function list_ajax_cdtold()
    {
        $time_start = microtime(true);

        $data = $this->input->post();
        $userId = $this->input->post("userid");
        $id_statut = $this->input->post("id_statut");
        if (has_permission(CDP_PROFILE)) {
            if ($id_statut == REFUSED_CDT || $id_statut == REFUSED_CDP || $id_statut == REFUSED) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'h.date_creation',
                    4 => "u.nom",
                    5 => "h.commentaire",
                    6 => ""
                );
            } else if ($id_statut == WAIT_VALIDATION_CDP) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'cdt.nom',
                    4 => "tfi.nom",
                    5 => "s.libelle"
                );
            } else if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == MISSING_STOCK) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "s.libelle"
                );
            } else if ($id_statut == SHIPPED || $id_statut == LIVRE) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.date_creation",
                    7 => "s.libelle"
                );
            } else if ($id_statut == RECEIVED) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.date_creation",
                    7 => "h.date_creation",
                    8 => "s.libelle"
                );
            } else {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'us.nom',
                    4 => 'u.nom',
                    5 => "s.libelle"
                );
            }
        } else {
            if ($id_statut == VALIDATED) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'h.date_creation',
                    4 => "u.nom",
                    5 => "h.commentaire",
                    6 => ''
                );
            } else if ($id_statut == RELIQUAT_SLA) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'h.date_creation',
                    4 => ''
                );
            } else if ($id_statut == CMD_PREPARER) {

                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "s.libelle"
                );
            } else if ($id_statut == LIVRE) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.commentaire",
                    7 => "s.libelle"
                );
            } else if ($id_statut == SHIPPED) {

                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.commentaire",
                    7 => "s.libelle"
                );
            } else if ($id_statut == RECEIVED) {

                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.date_creation",
                    7 => "h.date_creation",
                    8 => "s.libelle"
                );
            } elseif ($id_statut == WAIT_VALIDATION_CDT) {

                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "cdt.nom",
                    4 => "tfi.nom",
                    5 => "s.libelle"
                );
            } else {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "s.libelle"
                );
            }
        }

        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $order = $columns[$this->input->post('order')[0]['column']];

        $dir = $this->input->post('order')[0]['dir'];

        $search = $this->input->post('search')['value'];
        $filtre1 = $filtre2 = $filtre3 = null;
        if ($id_statut == WAIT_VALIDATION_CDT) {

            $filtre1 = substr($this->input->post('columns')[3]['search']['value'], 1, -1);
            $filtre2 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
            $filtre3 = substr($this->input->post('columns')[5]['search']['value'], 1, -1);
        } elseif (empty($id_statut)) {
            $filtre3 = $this->input->post("id_etat");
        }
        $countTotalData = count($this->CommandeTFIModel->liste_cdt_in($id_statut, $userId, null, null, $order, $dir, $filtre1, $filtre2, $filtre3, $search));
        $totalFiltered = $countTotalData;

        $posts = $this->CommandeTFIModel->liste_cdt_in($id_statut, $userId, $start, $limit, $order, $dir, $filtre1, $filtre2, $filtre3, $search);

        $data = array();
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $nestedData['id'] = $post->id;
                $nestedData['reference'] = $post->reference;

                $nestedData['created'] = date('d/m/Y', strtotime($post->date_creation));
                if ($id_statut == WAIT_VALIDATION_CDT) {

                    $nestedData['created_by'] = $post->nom_complet_cdt
                        . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                        . $post->nom_cdt_sup . ' ' . $post->nom_cdt_sup;
                    $nestedData['destinataire'] = $post->nom_complet_tfi
                        . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                        . $post->nom_tfi_sup . ' ' . $post->nom_tfi_sup;

                }

                $nestedData['etat'] = $post->etat_cmd;
                $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'>
                                                <i class=\"fa fa-eye\"></i></a>";

                if ($id_statut == RELIQUAT_SLA) {

                    $nestedData['reliquat'] = date('d/m/Y', strtotime($post->date_last_action));
                }
                if ($id_statut == VALIDATED) {

                    $nestedData['validation'] = date('d/m/Y', strtotime($post->date_last_action));
                    $nestedData['validee_par'] = $post->nom_last_action;
                    $nestedData['remarque'] = $post->commentaire_last_action;
                }
                if (in_array($id_statut, array(CMD_PREPARER, RECEIVED, SHIPPED, LIVRE))) {
                    $nestedData['validation'] = date('d/m/Y', strtotime($post->date_validation));
                    $nestedData['validee_par'] = $post->nom_validation;
                    $nestedData['remarque'] = $post->commentaire_validation;
                }
                if (in_array($id_statut, array(RECEIVED, SHIPPED))) {
                    $nestedData['expedition'] = ($post->date_expedition ? date('d/m/Y', strtotime($post->date_expedition)) : '---');
                    $post->colis = $this->CommandeTFIModel->getTotalColisByCmd($post->id);
                    $post->colis_recus = $this->CommandeTFIModel->getTotalColisRecusByCmd($post->id);
                    $nestedData['nbr_colis'] = $post->colis_recus->total_recus . "/" . $post->colis->total;
                }
                if ($id_statut == RECEIVED) {
                    $nestedData['reception'] = date('d/m/Y', strtotime($post->date_last_action));
                }
                if ($id_statut == LIVRE) {
                    $nestedData['expedition'] = ($post->date_expedition ? date('d/m/Y', strtotime($post->date_expedition)) : '---');
                }
                $data[] = $nestedData;

            }

            $time_stop = microtime(true) - $time_start;

            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval($countTotalData),
                "recordsFiltered" => intval($totalFiltered),
                "data" => $data,
                "time" => number_format($time_stop,2,".","").' sec'

            );

            echo json_encode($json_data);
        } else {

            $time_stop = microtime(true) - $time_start;

            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval(0),
                "recordsFiltered" => intval(0),
                "data" => [],
                "time" => number_format($time_stop,2,".","").' sec'

            );

            echo json_encode($json_data);
        }
    }


    public function list_ajax_cdt()
    {
        $time_start = microtime(true);

        $data = $this->input->post();

        $userId = $this->input->post("userid");
        $id_statut = $this->input->post("id_statut");
        $backup_cdp = $this->input->post("backup_cdp");

        if (has_permission(CDP_PROFILE)) {
            if ($id_statut == REFUSED_CDT || $id_statut == REFUSED_CDP || $id_statut == REFUSED) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'h.date_creation',
                    4 => "u.nom",
                    5 => "h.commentaire",
                    6 => ""
                );
            } else if ($id_statut == WAIT_VALIDATION_CDP) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'cdt.nom',
                    4 => "tfi.nom",
                    5 => "s.libelle"
                );

                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "c.nom_complet_cdt",
                    4 => "c.nom_complet_tfi",
                    5 => "c.etat_cmd"
                );
            } else if ($id_statut == VALIDATED || $id_statut == CMD_PREPARER || $id_statut == MISSING_STOCK) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "s.libelle"
                );
            } else if ($id_statut == SHIPPED || $id_statut == LIVRE) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.date_creation",
                    7 => "s.libelle"
                );
            } else if ($id_statut == RECEIVED) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.date_creation",
                    7 => "h.date_creation",
                    8 => "s.libelle"
                );
            } else {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'us.nom',
                    4 => 'u.nom',
                    5 => "s.libelle"
                );
            }
        } else {
            if ($id_statut == VALIDATED) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'h.date_creation',
                    4 => "u.nom",
                    5 => "h.commentaire",
                    6 => ''
                );
            } else if ($id_statut == RELIQUAT_SLA) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => 'h.date_creation',
                    4 => ''
                );
            } else if ($id_statut == CMD_PREPARER) {

                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "s.libelle"
                );
            } else if ($id_statut == LIVRE) {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.commentaire",
                    7 => "s.libelle"
                );
            } else if ($id_statut == SHIPPED) {

                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.commentaire",
                    7 => "s.libelle"
                );
            } else if ($id_statut == RECEIVED) {

                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "h1.date_creation",
                    4 => "u1.nom",
                    5 => "h1.commentaire",
                    6 => "h2.date_creation",
                    7 => "h.date_creation",
                    8 => "s.libelle"
                );
            } elseif ($id_statut == WAIT_VALIDATION_CDT) {

                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "c.nom_complet_cdt",
                    4 => "c.nom_complet_tfi",
                    5 => "c.etat_cmd",
                    6 => "c.type_commande"
                );
            } else {
                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "s.libelle"
                );
            }
        }

        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $order = $columns[$this->input->post('order')[0]['column']];

        $dir = $this->input->post('order')[0]['dir'];

        $search = $this->input->post('search')['value'];
        $filtre1 = $filtre2 = $filtre3 = null;
        if ($id_statut == WAIT_VALIDATION_CDT) {

            $filtre1 = substr($this->input->post('columns')[3]['search']['value'], 1, -1);
            $filtre2 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
            $filtre3 = substr($this->input->post('columns')[5]['search']['value'], 1, -1);
        } elseif (empty($id_statut)) {
            $filtre3 = $this->input->post("id_etat");
        }

        if ($id_statut == WAIT_VALIDATION_CDT) {

            $_POST["validation"] = "validation";
            //$_POST["epi_outillage"] = $epi_outillage = 'epi_outillage';
            $_POST["epi_outillage"] = $epi_outillage = null;

            $countTotalData = count($this->CommandeTFIModel->liste_cdt_all_cmd($id_statut, $userId, null, null, $order, $dir, null, null, null, null));
            $totalFiltered = count($this->CommandeTFIModel->liste_cdt_all_cmd($id_statut, $userId, null, null, $order, $dir, $filtre1, $filtre2, $filtre3, $search));
            $posts = $this->CommandeTFIModel->liste_cdt_all_cmd($id_statut, $userId, $start, $limit, $order, $dir, $filtre1, $filtre2, $filtre3, $search);
        }

        else {

            $countTotalData = count($this->CommandeTFIModel->liste_cdt_in($id_statut, $userId, null, null, $order, $dir, $filtre1, $filtre2, $filtre3, $search));
            $totalFiltered = $countTotalData;

            $posts = $this->CommandeTFIModel->liste_cdt_in($id_statut, $userId, $start, $limit, $order, $dir, $filtre1, $filtre2, $filtre3, $search);
        }

        $data = array();
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $nestedData['id'] = $post->id;
                $nestedData['reference'] = $post->reference;

                $nestedData['created'] = date('d/m/Y', strtotime($post->date_creation));

                if ($id_statut == WAIT_VALIDATION_CDT) {

                    $nestedData['created_by'] = $post->nom_complet_cdt
                        . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                        . $post->nom_cdt_sup . ' ' . $post->nom_cdt_sup;
                    $nestedData['destinataire'] = $post->nom_complet_tfi
                        . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                        . $post->nom_tfi_sup . ' ' . $post->nom_tfi_sup;
                    $nestedData['type_commande'] = $post->type_commande;

                    if($post->produit_log == 1) {

                        $epi_outillage =  ($post->comm_outillage_spe == 1) ? true : false;

                        if($userId != $this->user->getUser() OR $backup_cdp == 1) {

                            $nestedData['action'] = '<a class="btn btn-default" href="' . site_url('CommandeTFI/detail_pl_buckup/' . $post->id) . (($epi_outillage) ? "/epi_outillage" : "") . '"><i class="fa fa-eye"></i></a>';
                        }
                        else{


                            $nestedData['action'] = '<a class="btn btn-default" href="' . site_url('CommandeTFI/detail_pl/' . $post->id) . (($epi_outillage) ? "/epi_outillage" : "") . '"><i class="fa fa-eye"></i></a>';
                        }
                    }

                    else if($post->type_commande == 'classique') {

                        $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'><i class=\"fa fa-eye\"></i></a>";
                    }

                    else {

                        if($userId != $this->user->getUser() OR $backup_cdp == 1) {

                            $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail_pl_buckup/" . $post->id) . "'><i class=\"fa fa-eye\"></i></a>";
                        }

                        else{

                            $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail_pl/" . $post->id) . "'><i class=\"fa fa-eye\"></i></a>";
                        }
                    }
                }

                else{

                    $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'><i class=\"fa fa-eye\"></i></a>";

                }

                $nestedData['etat'] = $post->etat_cmd;

                if ($id_statut == RELIQUAT_SLA) {

                    $nestedData['reliquat'] = date('d/m/Y', strtotime($post->date_last_action));
                }
                if ($id_statut == VALIDATED) {

                    $nestedData['validation'] = date('d/m/Y', strtotime($post->date_last_action));
                    $nestedData['validee_par'] = $post->nom_last_action;
                    $nestedData['remarque'] = $post->commentaire_last_action;
                }
                if (in_array($id_statut, array(CMD_PREPARER, RECEIVED, SHIPPED, LIVRE))) {
                    $nestedData['validation'] = date('d/m/Y', strtotime($post->date_validation));
                    $nestedData['validee_par'] = $post->nom_validation;
                    $nestedData['remarque'] = $post->commentaire_validation;
                }
                if (in_array($id_statut, array(RECEIVED, SHIPPED))) {
                    $nestedData['expedition'] = ($post->date_expedition ? date('d/m/Y', strtotime($post->date_expedition)) : '---');
                    $post->colis = $this->CommandeTFIModel->getTotalColisByCmd($post->id);
                    $post->colis_recus = $this->CommandeTFIModel->getTotalColisRecusByCmd($post->id);
                    $nestedData['nbr_colis'] = $post->colis_recus->total_recus . "/" . $post->colis->total;
                }
                if ($id_statut == RECEIVED) {
                    $nestedData['reception'] = date('d/m/Y', strtotime($post->date_last_action));
                }
                if ($id_statut == LIVRE) {
                    $nestedData['expedition'] = ($post->date_expedition ? date('d/m/Y', strtotime($post->date_expedition)) : '---');
                }
                $data[] = $nestedData;

            }

            $time_stop = microtime(true) - $time_start;

            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval($countTotalData),
                "recordsFiltered" => intval($totalFiltered),
                "data" => $data,
                "time" => number_format($time_stop,2,".","").' sec'

            );

            echo json_encode($json_data);
        } else {

            $time_stop = microtime(true) - $time_start;

            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval(0),
                "recordsFiltered" => intval(0),
                "data" => [],
                "time" => number_format($time_stop,2,".","").' sec'

            );

            echo json_encode($json_data);
        }
    }

    public function list_ajax_cdp()
    {
        $time_start = microtime(true);
        $data = $this->input->post();
        $userId = $this->input->post("userid");
        $id_statut = $this->input->post("id_statut");

        if ($id_statut == VALIDATED) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => 'h.date_creation',
                4 => "u.nom",
                5 => "h.commentaire",
                6 => ''
            );
        } else if ($id_statut == RELIQUAT_SLA) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => 'h.date_creation',
                4 => ''
            );
        } else if ($id_statut == CMD_PREPARER) {

            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "h1.date_creation",
                4 => "u1.nom",
                5 => "h1.commentaire",
                6 => "s.libelle"
            );
        } else if ($id_statut == LIVRE) {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "h1.date_creation",
                4 => "u1.nom",
                5 => "h1.commentaire",
                6 => "h2.commentaire",
                7 => "s.libelle"
            );
        } else if ($id_statut == SHIPPED) {

            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "h1.date_creation",
                4 => "u1.nom",
                5 => "h1.commentaire",
                6 => "h2.commentaire",
                7 => "s.libelle"
            );
        } else if ($id_statut == RECEIVED) {

            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "h1.date_creation",
                4 => "u1.nom",
                5 => "h1.commentaire",
                6 => "h2.date_creation",
                7 => "h.date_creation",
                8 => "s.libelle"
            );
        } elseif ($id_statut == WAIT_VALIDATION_CDP) {

            /*$columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "cdt.nom",
                4 => "tfi.nom",
                5 => "s.libelle"
            );*/

            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "c.nom_complet_cdt",
                4 => "c.nom_complet_tfi",
                5 => "c.etat_cmd",
                6 => "c.type_commande"
            );
        } else {
            $columns = array(
                0 => 'c.reference',
                1 => 'c.id_cmd',
                2 => 'c.date_creation',
                3 => "s.libelle"
            );
        }

        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $order = $columns[$this->input->post('order')[0]['column']];
        $dir = $this->input->post('order')[0]['dir'];

        $search = $this->input->post('search')['value'];
        $filtre1 = $filtre2 = $filtre3 = null;

        if ($id_statut == WAIT_VALIDATION_CDP) {

            $filtre1 = substr($this->input->post('columns')[3]['search']['value'], 1, -1);
            $filtre2 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
            $filtre3 = substr($this->input->post('columns')[5]['search']['value'], 1, -1);
        } else if (empty($id_statut)) {
            $filtre3 = $this->input->post("id_etat");
        }

        if ($id_statut == WAIT_VALIDATION_CDP) {

            $_POST["validation"] = "validation";
            $_POST["epi_outillage"] = $epi_outillage = 'epi_outillage';

            $countTotalData = count($this->CommandeTFIModel->liste_cdp_all_cmd($id_statut, $userId, null, null, $order, $dir, null, null, null, null));
            $totalFiltered = count($this->CommandeTFIModel->liste_cdp_all_cmd($id_statut, $userId, null, null, $order, $dir, $filtre1, $filtre2, $filtre3, $search));
            $posts = $this->CommandeTFIModel->liste_cdp_all_cmd($id_statut, $userId, $start, $limit, $order, $dir, $filtre1, $filtre2, $filtre3, $search);
        }

        else {

            $countTotalData = count($this->CommandeTFIModel->liste_cdp_in($id_statut, $userId, null, null, $order, $dir, $filtre1, $filtre2, $filtre3, $search));
            $totalFiltered = $countTotalData;

            $posts = $this->CommandeTFIModel->liste_cdp_in($id_statut, $userId, $start, $limit, $order, $dir, $filtre1, $filtre2, $filtre3, $search);
        }


        $data = array();
        if (!empty($posts)) {

            foreach ($posts as $post) {

                $nestedData['id'] = $post->id;
                $nestedData['reference'] = $post->reference;

                if ($id_statut == WAIT_VALIDATION_CDP) {

                    $nestedData['created_by'] = $post->nom_complet_cdt
                        . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                        . $post->nom_cdt_sup . ' ' . $post->nom_cdt_sup;
                    $nestedData['destinataire'] = $post->nom_complet_tfi
                        . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                        . $post->nom_tfi_sup . ' ' . $post->nom_tfi_sup;
                    $nestedData['type_commande'] = $post->type_commande;

                    if($post->produit_log == 1) {

                        $epi_outillage =  ($post->comm_outillage_spe == 1) ? true : false;

                        if($userId != $this->user->getUser()) {

                            $nestedData['action'] = '<a class="btn btn-default" href="' . site_url('CommandeTFI/detail_pl_buckup/' . $post->id) . (($epi_outillage) ? "/epi_outillage" : "") . '"><i class="fa fa-eye"></i></a>';
                        }
                        else{
                            $nestedData['action'] = '<a class="btn btn-default" href="' . site_url('CommandeTFI/detail_pl/' . $post->id) . (($epi_outillage) ? "/epi_outillage" : "") . '"><i class="fa fa-eye"></i></a>';
                        }
                    }

                    else {

                        $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'><i class=\"fa fa-eye\"></i></a>";
                    }
                }

                else{

                    $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'><i class=\"fa fa-eye\"></i></a>";
                }

                $nestedData['created'] = date('d/m/Y', strtotime($post->date_creation));
                $nestedData['etat'] = $post->etat_cmd;

                if ($id_statut == RELIQUAT_SLA) {

                    $nestedData['reliquat'] = date('d/m/Y', strtotime($post->date_last_action));
                }

                if (in_array($id_statut, array(CMD_PREPARER, RECEIVED, SHIPPED, LIVRE))) {

                    $nestedData['validation'] = date('d/m/Y', strtotime($post->date_validation));
                    $nestedData['validee_par'] = $post->nom_validation . ' ' . $post->prenom_validation;
                    $nestedData['remarque'] = $post->commentaire_validation;
                }
                if ($id_statut == VALIDATED) {

                    $nestedData['validation'] = date('d/m/Y', strtotime($post->date_last_action));
                    $nestedData['validee_par'] = $post->nom_last_action . ' ' . $post->prenom_last_action;
                    $nestedData['remarque'] = $post->commentaire_last_action;
                }

                if (in_array($id_statut, array(RECEIVED, SHIPPED))) {

                    $nestedData['expedition'] = ($post->date_expedition ? date('d/m/Y', strtotime($post->date_expedition)) : '---');
                    $post->colis = $this->CommandeTFIModel->getTotalColisByCmd($post->id);
                    $post->colis_recus = $this->CommandeTFIModel->getTotalColisRecusByCmd($post->id);
                    $nestedData['nbr_colis'] = $post->colis_recus->total_recus . "/" . $post->colis->total;
                }

                if ($id_statut == RECEIVED) {

                    $nestedData['reception'] = date('d/m/Y', strtotime($post->date_last_action));
                }
                if ($id_statut == LIVRE) {

                    $nestedData['expedition'] = ($post->date_expedition ? date('d/m/Y', strtotime($post->date_expedition)) : '---');
                }

                $data[] = $nestedData;
            }
            $time_stop = microtime(true) - $time_start;
            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval($countTotalData),
                "recordsFiltered" => intval($totalFiltered),
                "data" => $data,
                "time" => number_format($time_stop,2,".","").' sec'
            );

            echo json_encode($json_data);
        } else {
            $time_stop = microtime(true) - $time_start;
            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval(0),
                "recordsFiltered" => intval(0),
                "data" => [],
                "time" => number_format($time_stop,2,".","").' sec'
            );

            echo json_encode($json_data);
        }
    }

    public function epiSpe()
    {
        $users = $this->UtilisateurModel->listeTFI($this->user->getUser());
        $this->load->view("CommandeTFI/epispe", array("users" => $users));
    }

    public function getEquipements($id)
    {
        $equipements = $this->CommandeTFIModel->liste_equipement($id);
        echo json_encode($equipements);

    }

    public function epiSpeTraitement()
    {
        $data = $this->input->post();
        if ($data["user"] == "") {
            $this->session->set_flashdata("error", 'Veillez sélectionner un utilisateur');
            redirect("CommandeTFI/epiSpe");
        } else {
            $equipement = $this->CommandeTFIModel->listeAllEquipement();
            $user = $this->UtilisateurModel->getUser($data["user"]);
            $nom = $user->nom;
            $prenom = $user->prenom;
            $qte = $data["qte"];
            $taille = $data["taille"];


            $file = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR . 'equipements' . DIRECTORY_SEPARATOR . 'EBM EPI-SECURITE.xlsx';
            $file2 = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR . 'equipements' . DIRECTORY_SEPARATOR . 'test.xlsx';

            $excel = PHPExcel_IOFactory::createReader('Excel2007');
            $excel = $excel->load($file);

            $excel->setActiveSheetIndex();
            $excel->getActiveSheet()->mergeCells('E2:F2')->setCellValue('E2', Date('d/m/Y'));
            $excel->getActiveSheet()->mergeCells('C3:F3')->setCellValue('C3', $nom);
            $excel->getActiveSheet()->mergeCells('C4:F4')->setCellValue('C4', $prenom);
            for ($i = 7; $i <= count($equipement) + 6; $i++) {
                if (isset($qte[$i - 6])) {
                    $excel->getActiveSheet()->setCellValue('E' . $i, $qte[$i - 6]);
                    if (isset($taille[$i - 6]) && $qte[$i - 6] != 0)
                        $excel->getActiveSheet()->setCellValue('F' . $i, $taille[$i - 6]);
                } else
                    $excel->getActiveSheet()->setCellValue('E' . $i, 0);
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition:inline; filename="EPI-SECURITE.xlsx"');
            header('Cache-Control: max-age=0');

            $objWriter = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            $objWriter->save('php://output');

        }
    }

    public function extractSla()
    {
        $this->load->view("CommandeTFI/extractSla");
    }

    public function extrat($date_fin, $nbr_mois)
    {
        $date_debut = strtotime('- ' . ($nbr_mois - 1) . ' month', strtotime($date_fin));
        $date_debut = date('Y-m-d', $date_debut);
        return $date_debut;

    }

    public function extractSlaTraitement()
    {
        set_time_limit(150);
        $monthnames = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
        $french_months = array('Jan', 'Fév', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc');
        $alphabet = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z','AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO','AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');
        $date_fin = $this->input->post("mois_fin");
        $nbr_mois = $this->input->post("nbr_mois");
        //$date_debut = $this->input->post("date_debut");
        $date_debut = $this->extrat($date_fin, $nbr_mois);
        /*var_dump($this->extrat($date_fin, $nbr_mois));
        die();*/
        $period = new DatePeriod(
            new DateTime($date_debut),
            new DateInterval('P1M'),
            new DateTime(date('Y-m-d', strtotime("+1 month", strtotime($date_fin))))
        );
        $d = array();
        $file = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR . 'equipements' . DIRECTORY_SEPARATOR . 'exemple-extract-SLA.xlsx';
        $file2 = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR . 'equipements' . DIRECTORY_SEPARATOR . 'test.xlsx';

        $excel = PHPExcel_IOFactory::createReader('Excel2007');
        $excel = $excel->load($file);
        $excel->setActiveSheetIndex(0);

        $count = 0;
        $alph_count = 2;
        $style = array(
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            )
        );
        $date_border_style = array('borders' => array('allborders' => array('style' =>
            PHPExcel_Style_Border::BORDER_THIN, 'color' => array('argb' => '000000'),)));


        foreach ($period as $key => $value) {

            $date_creation = $value->format('M-Y');
            $date_creation = str_replace($monthnames, $french_months, $date_creation);
            $m = $value->format('m');
            $y = $value->format('Y');
            $resultat = $this->CommandeTFIModel->qteValideeByProduit($y, $m);
            $reliquat_cdp = $this->CommandeTFIModel->qteValideeReliquatByProduitCDP($y, $m);
            $valide_cdp = $resultat;
            $refus_cdp = $this->CommandeTFIModel->qteExtractByproduitRefus($y, $m);
//var_dump("reliquat".$y."/".$m,$reliquat_cdp,"refus".$y."/".$m,$refus_cdp,"valid".$y."/".$m,$valide_cdp);
            $d[$date_creation] = $resultat;

            $j = 0;
            $excel->getActiveSheet()->mergeCells($alphabet[$alph_count] . "1:" . $alphabet[$alph_count + 1] . '1')->setCellValue($alphabet[$alph_count] . "1", $date_creation);
            $excel->getActiveSheet()->getStyle($alphabet[$alph_count] . "1:" . $alphabet[$alph_count + 1] . '1')->applyFromArray($date_border_style);

            $excel->getActiveSheet()->setCellValue($alphabet[$alph_count] . "2", "Validation CDP");
            $excel->getActiveSheet()->setCellValue($alphabet[$alph_count + 1] . "2", "Validation SLA");
            $excel->getActiveSheet()->getStyle($alphabet[$alph_count + 1] . "2")->applyFromArray($date_border_style);
            $excel->getActiveSheet()->getStyle($alphabet[$alph_count] . "2")->applyFromArray($date_border_style);
            foreach ($resultat as $produit) {
                if ($count == 0) {
                    $excel->getActiveSheet()->setCellValue('A' . (3 + $j), $produit->reference_free);
                    $excel->getActiveSheet()->setCellValue('B' . (3 + $j), $produit->designation);
                    $excel->getActiveSheet()->getRowDimension(3 + $j)->setRowHeight(20);
                    $excel->getActiveSheet()->getStyle('B' . (3 + $j))->applyFromArray($date_border_style);
                    $excel->getActiveSheet()->getStyle('A' . (3 + $j))->applyFromArray($date_border_style);
                    $j++;

                }

            }
            $i = 0;
            foreach ($resultat as $key => $produit) {
                $excel->getActiveSheet()->setCellValue($alphabet[$alph_count + 1] . (3 + $i), ((isset($produit->quantite) ? $produit->quantite : 0)));
                $excel->getActiveSheet()->getStyle($alphabet[$alph_count + 1] . (3 + $i))->applyFromArray($style);
                $excel->getActiveSheet()->getStyle($alphabet[$alph_count + 1] . (3 + $i))->applyFromArray($date_border_style);
                $i++;


            }
            $k = 0;
            foreach ($valide_cdp as $key => $produit) {
                //        var_dump($valide_cdp,"---",$reliquat_cdp,"-----",$refus_cdp);
                $excel->getActiveSheet()->setCellValue($alphabet[$alph_count] . (3 + $k), ((isset($valide_cdp[$key]->quantite) ? $valide_cdp[$key]->quantite : 0)) + ((isset($refus_cdp[$key]->quantite) ? $refus_cdp[$key]->quantite : 0)) + ((isset($reliquat_cdp[$key]->quantite) ? $reliquat_cdp[$key]->quantite : 0)));
                $excel->getActiveSheet()->getStyle($alphabet[$alph_count] . (3 + $k))->applyFromArray($style);
                $excel->getActiveSheet()->getStyle($alphabet[$alph_count] . (3 + $k))->applyFromArray($date_border_style);
                $k++;


            }
            $alph_count = $alph_count + 2;
            $count++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition:inline; filename="extract.xlsx"');
        header('Cache-Control: max-age=0');

        $objWriter = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $objWriter->save('php://output');
    }

    public function CommandeLivre()
    {

        $command_livre = $this->CommandeTFIModel->CommandeLivre();
        foreach ($command_livre as $cmd) {
            $colis = $this->ColisModel->colis_recu($cmd->id_cmd);
            if ($cmd->nbrcolis == $colis->colis_recu) {
                echo " Reference de la commande : " . $cmd->reference . "<br>id de la commande : " . $cmd->id_cmd . "<br>----------------------------------------------------------<br>";
            }
        }
    }

    public function list_ajax_equipes()
    {
        $time_start = microtime(true);
        $data = $this->input->post();
        $userId = $this->input->post("userid");
        $id_statut = $this->input->post("id_statut");
        if (has_permission(CDP_PROFILE)) {

            if ($id_statut == WAIT_VALIDATION_BY_EQUIPE_CDP) {

                $columns = array(
                    0 => 'c.reference',
                    1 => 'c.id_cmd',
                    2 => 'c.date_creation',
                    3 => "cdt.nom",
                    4 => "tfi.nom",
                    5 => "s.libelle",
                    6 => "type_commande"
                );
            }
        }

        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $order = $columns[$this->input->post('order')[0]['column']];

        $dir = $this->input->post('order')[0]['dir'];

        $search = $this->input->post('search')['value'];
        $filtre1 = $filtre2 = $filtre3 = null;
        if ($id_statut == WAIT_VALIDATION_CDT) {

            $filtre1 = substr($this->input->post('columns')[3]['search']['value'], 1, -1);
            $filtre2 = substr($this->input->post('columns')[4]['search']['value'], 1, -1);
            $filtre3 = substr($this->input->post('columns')[5]['search']['value'], 1, -1);
        }
        $countTotalData = count($this->CommandeTFIModel->liste_cdt_in(WAIT_VALIDATION_CDT, $userId, null, null, $order, $dir, $filtre1, $filtre2, $filtre3, $search));
        $totalFiltered = $countTotalData;

        $posts = $this->CommandeTFIModel->liste_cdt_in(WAIT_VALIDATION_CDT, $userId, $start, $limit, $order, $dir, $filtre1, $filtre2, $filtre3, $search);

        $data = array();
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $nestedData['id'] = $post->id;
                $nestedData['reference'] = $post->reference;
                $nestedData['type_commande'] = $post->type_commande;
                $nestedData['created'] = date('d/m/Y', strtotime($post->date_creation));
                $nestedData['created_by'] = $post->nom_complet_tfi;
                $nestedData['destinataire'] = $post->nom_complet_tfi;
                $nestedData['etat'] = $post->etat_cmd;
                $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $post->id) . "'>
                                                <i class=\"fa fa-eye\"></i></a>";
                $nestedData['created_by'] = $post->nom_complet_tfi
                    . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $post->prenom_cdt_sup . ' ' . $post->nom_cdt_sup;
                $nestedData['destinataire'] = $post->nom_complet_tfi
                    . "<br/><span style='font-size:13px;color:#777;'>Sup: "
                    . $post->prenom_tfi_sup . ' ' . $post->nom_tfi_sup;
                $data[] = $nestedData;
            }

            $time_stop = microtime(true) - $time_start;
            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval($countTotalData),
                "recordsFiltered" => intval($totalFiltered),
                "data" => $data,
                "time" => number_format($time_stop,2,".","").' sec'
            );

            echo json_encode($json_data);
        } else {
            $time_stop = microtime(true) - $time_start;
            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval(0),
                "recordsFiltered" => intval(0),
                "data" => [],
                "time" => number_format($time_stop,2,".","").' sec'
            );

            echo json_encode($json_data);
        }
    }

    public function nbrCommandeByWeek($semaine)
    {
        $cmds = $this->CommandeTFIModel->nbrCommandeByWeek();
        $check = false;
        foreach ($cmds as $cmd) {
            $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($cmd->id);
            $i = 0;
            $j = 0;
            $k = 0;
            foreach ($liste_commande_produit as $produit) {
                $dispo = (($produit->stock_arg - $produit->stock_virtuel) - ($produit->quantite - ((!$produit->quantite_expediee) ? 0 : $produit->quantite_expediee)));

                if ($dispo < 0) {
                    $i++;
                }
                if ($dispo > 0) {
                    $j++;
                }
                $k++;

            }
            if ($j > 0) {
                $check = true;
            }
        }
        echo json_encode(array($check, $semaine));
    }

    public function extractProduitsCVS()
    {
        $date_debut = $this->input->post("date_debut");
        $date_fin = $this->input->post("date_fin");
        $d = new DateTime($date_fin);
        $d->modify("+1 day");
        $date_fin = $d->format('Y-m-d');
        $liste_produits = $this->ProduitModel->produitsExtract();
        $list_cdp = $this->BuckupModel->GetAllCdp2();
        $qte_sla = [];
        foreach ($liste_produits as $key => $produit) {
            //  $this->CommandeTFIModel->qteValideeByProduitCDP($produit->id_produit,103);
            $data_cdp = $this->ProduitModel->qteExtractByproduitCdp($date_debut, $date_fin, $produit->id_produit);
            $data_reliquat = $this->ProduitModel->qteExtractByproduitReliquat($date_debut, $date_fin, $produit->id_produit);
            $data_sla = $this->ProduitModel->qteExtractByproduitValide($date_debut, $date_fin, $produit->id_produit);
            $data_refus = $this->ProduitModel->qteExtractByproduitRefus($date_debut, $date_fin, $produit->id_produit);
            $quantite = 0;
            $cdp_quantite = array();
            foreach ($list_cdp as $cdp) {
                $name = $cdp->nom . " " . $cdp->prenom;
                $key_cdp = array_search($cdp->nom . " " . $cdp->prenom, array_column($data_cdp, 'cdp'));
                $key_reliquat = array_search($cdp->nom . " " . $cdp->prenom, array_column($data_reliquat, 'cdp'));
                $key_refuse = array_search($cdp->nom . " " . $cdp->prenom, array_column($data_refus, 'cdp'));
                $key_sla = array_search($cdp->nom . " " . $cdp->prenom, array_column($data_sla, 'cdp'));
                $quantite = ((isset($data_cdp[$key_cdp]["quantite"]) && $key_cdp !== false) ? $data_cdp[$key_cdp]["quantite"] : 0) + ((isset($data_reliquat[$key_reliquat]["quantite"]) && $key_reliquat !== false) ? $data_reliquat[$key_reliquat]["quantite"] : 0) + ((isset($data_refus[$key_refuse]["qte_refuser"]) && $key_refuse !== false) ? $data_refus[$key_refuse]["qte_refuser"] : 0) + ((isset($data_sla[$key_sla]["quantite"]) && $key_sla !== false) ? $data_sla[$key_sla]["quantite"] : 0);
                array_push($cdp_quantite, ["cdp" => $name, "qte" => $quantite]);
                array_push($qte_sla, $this->CommandeTFIModel->qteValideeByProduitsla($date_debut, $date_fin, $produit->id_produit, $cdp->id_user));
            }
            $liste_produits[$key]->quantite = (object)$cdp_quantite;
            $liste_produits[$key]->qtecdp_att_sla = $this->ProduitModel->qteValideCDPAttSLA($date_debut, $date_fin, $produit->id_produit);
            $liste_produits[$key]->qte_reliquats = $this->CommandeTFIModel->getCountProduitRelequit($produit->id_produit)->somme;
        }
        $fp = realpath("./public/csv_file");
        $file = fopen($fp . '/produits.csv', 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        $header = array('Catégorie', "Désignation", "Ref Free", "ARG Stock", "ARG Stock virtuel", 'Validé CDP/ Attente SLA', 'Reliquats SLA');
        foreach ($list_cdp as $cdp) {
            $name = $cdp->nom . " " . $cdp->prenom;
            array_push($header, $name);
            array_push($header, "Validation SLA");
        }
        fputcsv($file, $header, ";");
        $compteur = 0;
        var_dump($data_cdp);
        foreach ($liste_produits as $key => $value) {


            $array = array($value->categorie, $value->designation, $value->reference_free, $value->stock_arg, (($value->stock_virtuel) ? ($value->stock_arg - $value->stock_virtuel + $value->stock_publi) : ($value->stock_arg + $value->stock_publi)), (($value->qtecdp_att_sla) ? $value->qtecdp_att_sla : 0), $value->qte_reliquats);

            foreach ($value->quantite as $v) {
                array_push($array, $v["qte"]);
                array_push($array, $qte_sla[$compteur]->quantite);
                $compteur++;
            }
            fputcsv($file, $array, ";");
        }

        fclose($file);
        // echo json_encode(true);*/

    }

    public function extractProduitsStock()
    {
        $date_debut = $this->input->post("date_debut");
        $date_fin = $this->input->post("date_fin");
        $d = new DateTime($date_fin);
        $d->modify("+1 day");
        $date_fin = $d->format('Y-m-d');
        $fp = realpath("./public/csv_file");
        $file = fopen($fp . '/stocks_produits.csv', 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        $header = array('N° de colis', "Date", "Ref Commande", "ID Produit", "Désignation", 'Référence Free', 'Quantité', 'Destinataire', 'UPR');

        fputcsv($file, $header, ";");
        $liste_colis_par_produit = $this->CommandeTFIModel->CommandeValiderParProduit($date_debut, $date_fin);
        foreach ($liste_colis_par_produit as $key => $value) {
            //   var_dump($value);
            $cdp = $this->UtilisateurModel->getCdpOfUser($value->tfi_id, $value->role);
            // var_dump($cdp,$value->role);
            if ($value->id_parent && $value->id_parent != -1)
                $array = array($value->id_parent, date_fr($value->date_creation), $value->reference, $value->id_produit, $value->designation, $value->reference_free, $value->qte, $value->tfi, $cdp->nom . " " . $cdp->prenom);
            else
                $array = array($value->id_colis, date_fr($value->date_creation), $value->reference, $value->id_produit, $value->designation, $value->reference_free, $value->qte, $value->tfi, $cdp->nom . " " . $cdp->prenom);

            fputcsv($file, $array, ";");
        }

        fclose($file);
        // echo json_encode(true);*/

    }

    public function extractProduitsValiderCDP()
    {
        $date_debut = $this->input->post("date_debut");
        $date_fin = $this->input->post("date_fin");
        $d = new DateTime($date_fin);
        $d->modify("+1 day");
        $date_fin = $d->format('Y-m-d');
        $fp = realpath("./public/csv_file");
        $file = fopen($fp . '/quantite_valider_cdp.csv', 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        $header = array('N° de commande', "Date", "Ref Commande", "ID Produit", "Désignation", 'Référence Free', 'Quantité', 'Destinataire', 'URP');

        fputcsv($file, $header, ";");
        $liste_colis_par_produit = $this->CommandeTFIModel->CommandeValiderParCDP($date_debut, $date_fin);
        foreach ($liste_colis_par_produit as $key => $value) {
            //   var_dump($value);
            $cdp = $this->UtilisateurModel->getCdpOfUser($value->tfi_id, $value->role);
            if ($value->tfi_id != $cdp->id_user) {
                $qte_refuse_sla = ($this->CommandeTFIModel->CommanderefuseParSla($value->id_produit, $value->id_cmd)) ? $this->CommandeTFIModel->CommanderefuseParSla($value->id_produit, $value->id_cmd)->qte : '0';
                $qte = $qte_refuse_sla + $value->qte;
                $array = array($value->id_cmd, date_fr($value->date_creation), $value->reference, $value->id_produit, $value->designation, $value->reference_free, $qte, $value->tfi, $cdp->nom . " " . $cdp->prenom);
                fputcsv($file, $array, ";");
            }
        }

        fclose($file);
        // echo json_encode(true);*/

    }

    public function attentevalidation()
    {
        $liste_produits = $this->ProduitModel->listeProduits();
        foreach ($liste_produits as $key => $value) {
            $liste_produits[$key]->qte_valider_sla = $this->CommandeTFIModel->getCountProduitvaliderSLA($value->id_produit)->somme;
        }
        $etat = $this->ProduitModel->getStatutStockARG($this->user->getUser());
        $this->load->view('Produit/attende_validation', ["liste_produits" => $liste_produits, "etat" => $etat]);
    }

    public function Attente_validation_SLA($id_statut = null)
    {
        $upr = $this->CommandeTFIModel->getAllUpr();
        $this->load->view('CommandeTFI/Attente_validation_SLA', [
            "id_statut" => $id_statut, "upr" => $upr,
        ]);
    }

    public function detailCommande($id)
    {
        $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($id);
        echo json_encode(array('code' => '200', 'produits_colis' => $liste_commande_produit));
    }

    public function countCmdByUser()
    {
        $data = $this->input->post();
        $livraison = $data["livraisaon"];
        $destinataire = $data["destinataire"];
        $count = $this->CommandeTFIModel->countCmdByUser($livraison, $destinataire);
        echo json_encode(array("count" => $count));
    }

    public function fusionColis($livraison, $destinataire, $id_cmd)
    {
        $commandes = $this->CommandeTFIModel->CmdByUser($livraison, $destinataire);
        $livraison_type = $this->LivrerModel->getTypeLivree($livraison);
        $status = $this->CommandeTFIModel->getLastStatus($commandes[0]->id);
        $produits = [];
        $ids = [];

        foreach ($commandes as $commande) {
            $ids[] = $commande->id;
        }
        $liste_commande_produit = $this->CommandeTFIModel->listeCommandesProduit($ids);
        $som = [];
        $som_qte = [];
        $last_id = 0;
        $i = 0;
        foreach ($liste_commande_produit as $key => $cmd) {
            if ($last_id != $cmd->id_produit) {
                $i++;
                $som[$i] = 0;
                $som_qte[$i] = 0;
            }
            $qte_colis = $this->CommandeTFIModel->getQteColisByProd($cmd->id_cmd, $cmd->id_produit, $cmd->reference);
            $liste_commande_produit[$key]->qte_Reliquats = $this->CommandeTFIModel->getCountProduitRelequit($cmd->id_produit)->somme;
            $liste_commande_produit[$key]->stock_produit = $this->CommandeTFIModel->listeCommandeProduitStockByUser($cmd->id_user, $cmd->id_produit)->stock_produit;

            if (!has_permission(ADMIN_PROFILE) && (has_permission(SLA_PROFILE) || has_permission(SLAN_PROFILE))) {
                $stock_tfi = $this->CommandeTFIModel->listeCommandeProduitStockByReference($cmd->id_user, $cmd->id_produit, $cmd->reference);

                $liste_commande_produit[$key]->qte_stock_tfi = ($stock_tfi) ? $stock_tfi->qte_stock_tfi : 0;
                $liste_commande_produit[$key]->qte_stock_transit = ($stock_tfi) ? $stock_tfi->qte_stock_transit : 0;
                $liste_commande_produit[$key]->qte_stock_virtuel_clr = ($stock_tfi) ? $stock_tfi->qte_stock_virtuel_clr : 0;
                $liste_commande_produit[$key]->qte_stock_virtuel = ($stock_tfi) ? $stock_tfi->qte_stock_virtuel : 0;
            }
            if ($qte_colis == null)
                $qte_colis = 0;

            //

            if (($cmd->id_categorie == ID_PRODUIT_COUTEUX || $cmd->id_categorie == ID_PRODUIT_EPISPE) && $cmd->reference != null && !empty($cmd->reference))
                $liste_commande_produit[$key]->quantite = 1;

            //  Quantité manquante à expédier
            $liste_commande_produit[$key]->qte_manquante = $cmd->quantite - $qte_colis;
            $som[$i] += $cmd->quantite - $qte_colis;
            $som_qte[$i] += $cmd->quantite;
            $last_id = $cmd->id_produit;

        }

        $i = 0;
        $last_id = 0;
        foreach ($liste_commande_produit as $key => $cmd) {
            if ($last_id != $cmd->id_produit) {
                $i++;
            }

            $liste_commande_produit[$key]->som_qte_manquante = $som[$i];
            $liste_commande_produit[$key]->som_qte = $som_qte[$i];
            $last_id = $cmd->id_produit;
        }


        $this->load->view('CommandeTFI/fusioncolis', ["commandes" => $commandes, "id_cmd" => $id_cmd, "liste_commande_produit" => $liste_commande_produit, "livraison" => $livraison_type, "status" => $status]);
    }

    public function expedier_multiple()
    {
        $data = $this->input->post();
        $this->db->trans_begin();
        $res = array('reponse' => '', 'erreur' => '0');
        $produits = $data["productsToAdd"];
        //  var_dump($produits);
        $last_id = 0;
        $index = 0;
        $last_cmd = 0;
        $cmd = [];
        $i = 0;
        $all_cmd = [];
        foreach ($produits as $key => $produit) {
            $all_cmd[] = $produits[$key]["commande"];
            $produits[$key]["sum_qte"] = 0;
        }
        foreach ($all_cmd as $key => $ancien) {

            $check = false;
            foreach ($cmd as $value) {
                if ($value == $ancien) {
                    $check = true;
                }
            }
            if (!$check) {

                $cmd[$i] = $all_cmd[$key];
                $i++;
            }


        }
        foreach ($produits as $key => $produit) {
            if ($produits[$key]["ancien_reference"] == "") {
                if ($produits[$key]["id_produit"] != $last_id) {
                    $index = $key;
                    $produits[$key]["sum_qte"] = $produits[$key]["quantite"];
                    $last_id = $produits[$key]["id_produit"];
                } else {
                    $produits[$index]["sum_qte"] += $produits[$key]["quantite"];
                    unset($produits[$key]);
                }
            }

        }

        //  var_dump($data);


        $data_colis = [];
        $tracking_ups = $this->randStrGen(12);
        $commentaire = $data["commentaire"];

        $data_colis["poids"] = $data["poids"];
        $data_colis["largeur"] = $data["largeur"];
        $data_colis["longeur"] = $data["longeur"];
        $data_colis["hauteur"] = $data["hauteur"];
        $data_colis["date_expedition"] = date('Y-m-d H:i:s');
        $data_colis["comment_expedition"] = $commentaire;
        $data_colis["cree_par"] = $this->user->getUser();
        $data_colis["id_cmd"] = Null;
        $data_colis["cree_par"] = $this->user->getUser();
        //      $data_colis["date_creation"] = date('Y-m-d H:i:s');
        $data_colis["id_parent"] = -1;
        $resultat = $this->LivrerModel->findLivreeCmd($cmd[0]);
        if ($resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR)
            $data_colis["id_statutcolis"] = COLIS_PREPARER;
        if ($resultat->type_livree == (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03))
            $data_colis["id_statutcolis"] = DELIVERED_PACKAGE;
        $id_colis = $this->CommandeTFIModel->ajouterColis($data_colis);
        $colis = [];
        foreach ($cmd as $key => $value) {
            $data_colis["id_cmd"] = $value;
            $data_colis["id_parent"] = $id_colis;
            $colis[$value] = $this->CommandeTFIModel->ajouterColis($data_colis);
        }
        // var_dump($produits);
        foreach ($produits as $key => $value) {
            $dataElementsColis["id_colis"] = $id_colis;
            $dataElementsColis["id_produit"] = $value["id_produit"];
            $dataElementsColis["quantite_recue"] = 0;
            if ($value["categorie"] == ID_PRODUIT_COUTEUX || $value["categorie"] == ID_PRODUIT_EPISPE) {
                $dataElementsColis["quantite"] = $value["quantite"];
                if ($value["categorie"] == ID_PRODUIT_EPISPE) {
                    $dataElementsColis['reference'] = $value["ancien_reference"];
                    $dataElementsColis['reference_epi'] = $value["reference"];
                } else {
                    $dataElementsColis['reference'] = $value["reference"];
                    $dataElementsColis['reference_epi'] = "";
                }
            } else {
                $dataElementsColis["quantite"] = $value["sum_qte"];
                $dataElementsColis['reference'] = "";
                $dataElementsColis['reference_epi'] = "";
            }
            $this->CommandeTFIModel->ajouterElementColisSingl($dataElementsColis);
        }

        foreach ($data["productsToAdd"] as $key => $value) {

            $dataElementColis["id_colis"] = $colis[$value["commande"]];
            $dataElementColis["id_produit"] = $value["id_produit"];
            $dataElementColis["quantite_recue"] = 0;
            if ($value["categorie"] == ID_PRODUIT_COUTEUX || $value["categorie"] == ID_PRODUIT_EPISPE) {

                $dataElementColis["quantite"] = $value["quantite"];
                if ($value["categorie"] == ID_PRODUIT_EPISPE) {
                    $dataElementColis['reference'] = $value["ancien_reference"];
                    $dataElementColis['reference_epi'] = $value["reference"];
                } else {
                    $dataElementColis['reference'] = $value["reference"];
                    $dataElementColis['reference_epi'] = "";
                    // var_dump($value["ancien_reference"]);
                }

                $this->ColisModel->UpdateStckProdTFI2($value["commande"], $value["id_produit"], $value["quantite"], $value["ancien_reference"]);

                $this->ColisModel->UpdateRefStckProdTFI2($value["commande"], $value["id_produit"], $dataElementColis['reference'], $value["ancien_reference"]);
            } else {
                $dataElementColis["quantite"] = $value["quantite"];
                $dataElementColis['reference'] = "";
                $dataElementsColis['reference_epi'] = "";
                $this->ColisModel->UpdateStckProdTFI2($value["commande"], $value["id_produit"], $value["quantite"]);

            }
            $this->ProduitModel->modifierStockArg2($value["id_produit"], $value["quantite"]);
            $this->CommandeTFIModel->ajouterElementColisSingl($dataElementColis);
            unset($dataElementColis);
        }

        if ($resultat->type_livree == MODE_LIVRAISON_ARG_ENVOI_UPS || $resultat->type_livree == MODE_LIVRAISON_ARG_RETRAIT_CLR) {
            $info_colis = array('id_user' => $data["id_tfi"], 'Length' => $data["longeur"], 'Width' => $data["largeur"], 'Height' => $data["hauteur"], 'Weight' => $data["poids"]);
            $retour = $this->UPSShipping($info_colis, $cmd[0]);
            // var_dump($retour);
            if ($retour['errors'] == 1) {
                $res = array('reponse' => $retour['results']['Description'], 'erreur' => $retour['errors']);
                $this->session->set_flashdata('item', array('id_colis' => $id_colis, 'message' => $retour['results']['Description'], 'class' => 'danger'));
                $this->db->trans_rollback();
            } else if ($retour['errors'] == 2) {
                $res = array('reponse' => $retour['results'], 'erreur' => $retour['errors']);
                $this->session->set_flashdata('item', array('id_colis' => $id_colis, 'message' => $retour['results'], 'class' => 'danger'));
                $this->db->trans_rollback();
            }
            if ($retour['errors'] == 0) {

                $data_colis_update["tracking_ups"] = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['TrackingNumber'];
                $code_barre = $retour['results']['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage'];
                $data_colis_update["date_expedition"] = date('Y-m-d H:i:s');
                $this->CommandeTFIModel->changeStatusColis($id_colis, $data_colis_update);
                foreach ($colis as $key => $value) {
                    $this->CommandeTFIModel->changeStatusColis($value, $data_colis_update);
                    $this->GenerateImageColis($code_barre, $value);
                }
                $this->GenerateImageColis($code_barre, $id_colis);
                $this->session->set_flashdata('item', array('id_colis' => $id_colis, 'message' => 'Le colis a été expédié avec succès.', 'class' => 'success'));
            }
        }

        /**/
        foreach ($cmd as $key => $value) {
            $commande_colis = $this->CommandeTFIModel->getColisByCmd($value);
            $shipped_colis_count = 0;
            $prepare_colis_count = 0;
            $livre_colis_count = 0;
            $somme_colis = 0;
            foreach ($commande_colis as $colis) {
                if ($colis->id_statutcolis == DELIVERED_PACKAGE) {
                    $shipped_colis_count++;
                }
                if ($colis->id_statutcolis == RECEIVED_PACKAGE) {
                    $livre_colis_count++;
                }
                if ($colis->id_statutcolis == COLIS_PREPARER) {
                    $prepare_colis_count++;
                }
            }
            $count_produitcmd = $this->CommandeTFIModel->countProduitCmd($value);
            $count_produitcolis = $this->CommandeTFIModel->countProduitColis($value);
            $somme_colis = $shipped_colis_count + $livre_colis_count;

            if ($shipped_colis_count == count($commande_colis) && count($commande_colis) > 0 && $count_produitcmd->qte_produit_cmd == $count_produitcolis->qte_produit_colis) {
                $data_statut = array();
                $data_statut["id_cmd"] = $value;
                $data_statut["id_statcmd"] = LIVRE;
                $data_statut["cree_par"] = $this->user->getUser();
                $data_statut["date_creation"] = date('Y-m-d H:i:s');
                $this->CommandeTFIModel->changeStatus($data_statut);
            } elseif ($somme_colis == count($commande_colis) && count($commande_colis) > 0 && $somme_colis > 0 && $count_produitcmd->qte_produit_cmd == $count_produitcolis->qte_produit_colis) {
                $data_statut = array();
                $data_statut["id_cmd"] = $value;
                $data_statut["id_statcmd"] = LIVRE;
                $data_statut["cree_par"] = $this->user->getUser();
                $data_statut["date_creation"] = date('Y-m-d H:i:s');
                $this->CommandeTFIModel->changeStatus($data_statut);
            } elseif ($count_produitcmd->qte_produit_cmd == $count_produitcolis->qte_produit_colis && $prepare_colis_count > 0 && count($commande_colis) > 0 && $resultat->type_livree != (MODE_LIVRAISON_ARG_RSP || MODE_LIVRAISON_RETRAIT_SUR_ARG_UPR03)) {
                $data_statut = array();
                $data_statut["id_cmd"] = $value;
                $data_statut["id_statcmd"] = CMD_PREPARER;
                $data_statut["cree_par"] = $this->user->getUser();
                $data_statut["date_creation"] = date('Y-m-d H:i:s');
                $this->CommandeTFIModel->changeStatus($data_statut);
            } else if ($count_produitcmd->qte_produit_cmd != $count_produitcolis->qte_produit_colis) {
                $data_statut = array();
                $data_statut["id_cmd"] = $value;
                $data_statut["id_statcmd"] = MISSING_STOCK;
                $data_statut["cree_par"] = $this->user->getUser();
                $data_statut["date_creation"] = date('Y-m-d H:i:s');
                $this->CommandeTFIModel->changeStatus($data_statut);
            }
        }


        /**/
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
        }
        $res = array('respone' => 'a ete expediter', 'erreur' => '0', 'id_colis' => reset($colis), 'id_cmd' => $data["cmd"]);

        echo json_encode($res);
        //  var_dump($colis);
        //  die();
    }

    public function recevoirMulti()
    {
        $data = $this->input->post();

        $this->db->trans_start();

        $id_colis = $data["id_colis"];
        $commande_courante = $this->CommandeTFIModel->getCmdByColis($id_colis);
        $idtfi = $data["idtfi"];
        if (isset($data["commentaire"]))
            $commentaire = $data["commentaire"];
        $all_colis = $this->CommandeTFIModel->getOtherColisMulti($id_colis);
        $colis_temp = [];
        foreach ($all_colis as $colis) {
            $colis_temp[] = $this->CommandeTFIModel->getColisById($colis->id_colis);
        }
        $data_colis["id_statutcolis"] = RECEIVED_PACKAGE;
        $data_colis["date_reception"] = date('Y-m-d H:i:s');
        if (isset($commentaire))
            $data_colis["comment_reception"] = $commentaire;
        $liste_colis_produit = $data['productsToAdd'];
        foreach ($colis_temp as $colis1) {
            $id_cmd = $colis1->id_cmd;
            $this->CommandeTFIModel->changeStatusColis($colis1->id_colis, $data_colis);
            foreach ($liste_colis_produit as $key => $colis) {

                if ($colis1->id_parent == -1) {
                    if ($colis['reference'] != null && !empty($colis['reference']))
                        $this->CommandeTFIModel->updateElementColis(array('quantite_recue' => $colis['quantite']), array('id_colis' => $colis1->id_colis, 'id_produit' => $colis['id_produit'], 'reference' => $colis['reference']));
                    else
                        $this->CommandeTFIModel->updateElementColis(array('quantite_recue' => $colis['quantite']), array('id_colis' => $colis1->id_colis, 'id_produit' => $colis['id_produit']));

                } else if ($colis1->id_colis == $colis["id_colis"]) {
                    $prod_tfi = $this->CommandeTFIModel->getProdTFI($colis1->id_cmd, $idtfi, $colis['id_produit'], $colis['reference']);
                    if ($prod_tfi) {
                        $data_update["stock_tfi"] = $prod_tfi->stock_tfi + $colis['quantite'];
                        $stock_transit = $prod_tfi->stock_transit - $colis['ancien_quantite'];
                        if ($stock_transit >= 0) {
                            $data_update["stock_transit"] = $stock_transit;
                        }
                        $this->CommandeTFIModel->updateStockTFI($prod_tfi->id, $data_update);
                    } else {
                        $data_insert["id_produit"] = $colis['id_produit'];
                        $data_insert["id_user"] = $idtfi;
                        $data_insert["id_cmd"] = $colis1->id_cmd;
                        $data_insert["stock_tfi"] = $colis['quantite'];
                        $this->CommandeTFIModel->insertStockTFI($data_insert, '+');
                    }
                    if ($prod_tfi->id_categorie == ID_PRODUIT_COUTEUX || $prod_tfi->id_categorie == ID_PRODUIT_EPISPE) {
                        $data_rma_historique["id_user"] = $idtfi;
                        $data_rma_historique["id_etat_validation_rma"] = EN_SERVICE;
                        $data_rma_historique["date_creation"] = date('Y-m-d');
                        $data_rma_historique["id_produit_tfi"] = $prod_tfi->id;
                        $this->db->insert('rma_historique', $data_rma_historique);
                        $data_update_produit_tfi["id_etat_rma"] = EN_SERVICE;
                        $data_update_produit_tfi["id_etat_validation_rma"] = EN_SERVICE;
                        $this->CommandeTFIModel->updateStockTFI($prod_tfi->id, $data_update_produit_tfi);
                    }


                    if ($colis['reference'] != null && !empty($colis['reference']))
                        $this->CommandeTFIModel->updateElementColis(array('quantite_recue' => $colis['quantite']), array('id_colis' => $colis1->id_colis, 'id_produit' => $colis['id_produit'], 'reference' => $colis['reference']));
                    else
                        $this->CommandeTFIModel->updateElementColis(array('quantite_recue' => $colis['quantite']), array('id_colis' => $colis1->id_colis, 'id_produit' => $colis['id_produit']));


                    $id_cmd = $colis1->id_cmd;
                    $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($id_cmd);
                    $qte_total_colis = 0;
                    $qte_total_cmd = 0;

                    foreach ($liste_commande_produit as $key => $cmd) {
                        $qte_total_colis += $this->CommandeTFIModel->getQteColisByProd($id_cmd, $cmd->id_produit);
                        $qte_total_cmd += $this->CommandeTFIModel->getQteCmdByProd($id_cmd, $cmd->id_produit);
                    }

                    $shipped_colis = $this->CommandeTFIModel->getLivredColisByCmd($id_cmd);
                    if ($shipped_colis == 0 && $qte_total_cmd == $qte_total_colis) {
                        $data_statut["id_cmd"] = $id_cmd;
                        $data_statut["id_statcmd"] = RECEIVED;
                        $data_statut["cree_par"] = $this->user->getUser();
                        $data_statut["date_creation"] = date('Y-m-d H:i:s');
                        $this->CommandeTFIModel->changeStatus($data_statut);
                    }
                }
            }
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $message = "";
            $message .= "<span style='float: left'><b>Émetteur : </b><br>";
            $all_cmd = $this->CommandeTFIModel->getOtherColisCmd($id_colis);
            foreach ($all_cmd as $c) {
                $message .= $c->cree_par . "<br><span style='font-size:13px;color:#777;'>Ref Commande: $c->reference</span><br>";
            }
            //    $message .= $this->infoCommande(388);
            $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
            $message .= "<b>Reçue par : </b>" . $cree_par->prenom . " " . $cree_par->nom . "<br><br>";
            if (!empty($data["commentaire"])) {
                $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
            }
            $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité expédiée</th><th style='" . style_td_th . "' align=\"center\">Quantité reçue</th></tr>";

            foreach ($liste_colis_produit as $key => $colis) {
                $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $this->ProduitModel->getInfoProduit($colis['id_produit'])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($colis['id_produit'])->reference_free . "</i>)" . "<br/><span style='font-size:13px;color:#777;'>Ref Commande: " . $colis['ref'] . " </span>" . "</td><td style='" . style_td_th . "' align=\"center\">" . $colis['ancien_quantite'] . "</td><td style='" . style_td_th . "' align=\"center\">" . $colis['quantite'] . "</td></tr>";
            }
            $table .= "</table><br>";
            if (count($liste_colis_produit) > 0) {
                $message .= $table;
            }
            $destinataire = $this->getDestinataire($all_cmd{0}->id_cmd);
            $object = "[SLAM] - Colis reçu acquitté  - " . date('d/m/Y');
            $tabCopy = array();
            if ($idtfi != $this->user->getUser()) {
                $this->SendMail($destinataire, $message, $object, $tabCopy);
            }
        }
        echo json_encode(['erreur' => '0', 'reponse' => 'Le Colis à été Recus avec succès !!', 'id_colis' => $commande_courante->id_cmd]);

    }


    private function GenerateImageColis($code_barre, $id_colis, $renv = null)
    {
        $path = realpath(__DIR__ . '/../../') . "/colisimages/";

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        if ($renv) {
            if (unlink($path . "$id_colis" . '.jpg')) {
                echo 'success';
            } else {
                echo 'fail';
            }

        }
        $img = $code_barre;
        $img = str_replace('data:image/jpg;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);
        $file = $path . "$id_colis" . '.jpg';
        $success = file_put_contents($file, $data);


    }

    public function detail_pl($id, $epi_outillage = null)
    {
        $user_vacances=null;
        $commande = $this->CommandeTFIModel->getCommandePL($id);
        $liste_produits = $this->CommandeTFIModel->produitsPLByCMD($id);
        $this->load->view("CommandeTFI/detailpl", array("commande" => $commande, "liste_commande_produit" => $liste_produits, "epi_outillage" => $epi_outillage,"user_vacance"=>$user_vacances));
    }
    public function detail_pl_buckup($id, $epi_outillage = null)
    {
        $user_vacances=null;
        if (has_permission(CDT_PROFILE)) {
            $sup = $this->UtilisateurModel->getSuperviseurTFI($this->session->userdata("user_id"));
            $sup = $sup->superviseur;
            $cdp = $this->UtilisateurModel->getUser($sup);
            $user_vacances=$cdp->user_vacances;
        }
        $commande = $this->CommandeTFIModel->getCommandePL($id);
        $liste_produits = $this->CommandeTFIModel->produitsPLByCMD($id);
        $this->load->view("CommandeTFI/detailpl", array("commande" => $commande, "liste_commande_produit" => $liste_produits, "epi_outillage" => $epi_outillage,"user_vacance"=>$user_vacances));
    }

    public function validerPl($id, $epi_outillage = null)
    {
        $data = $this->input->post();
        if (has_permission(CDP_PROFILE) || (has_permission(CDT_PROFILE) && $data['status']==EN_ATTENTE_DE_VALIDATION_UPR_PL)) {
            $this->db->set("id_state", EN_ATTENTE_DE_VALIDATION_SLA_PL);
            $this->db->set("comment_upr", $data["commentaire"]);
            $this->db->set("date_validation_upr", date('Y-m-d H:i:s'));
            $this->db->where("id_cmd_pl", $id);
            $this->db->update("commandes_pl");

            $cmd_pl_historique_data = array(
                'id_cmd' => $id,
                'id_stat_cmd' => EN_ATTENTE_DE_VALIDATION_SLA_PL,
                'date_creation' => date('Y-m-d H:i:s'),
                'id_user' =>  ($this->user->getUser()) ? $this->user->getUser(): ID_ADMIN_CRON,
                'commentaire' => $data["commentaire"]
            );

            $this->db->insert("cmd_pl_historique", $cmd_pl_historique_data);

            foreach ($data["produit"] as $key => $value) {
                $this->db->set("qte_upr", $value[0]);
                $this->db->where("id_cmd_pl", $id);
                $this->db->where("id_produit", $key);
                $this->db->update("commandes_produit_logistique");
            }

        } else if (has_permission(SLA_PROFILE) || has_permission(SLAN_PROFILE)) {
            $this->db->set("id_state", VALIDER_PL);
            $this->db->set("comment_sla", $data["commentaire"]);
            $this->db->set("date_validation_sla", date('Y-m-d H:i:s'));
            $this->db->where("id_cmd_pl", $id);
            $this->db->update("commandes_pl");

            $cmd_pl_historique_data = array(
                'id_cmd' => $id,
                'id_stat_cmd' => VALIDER_PL,
                'date_creation' => date('Y-m-d H:i:s'),
                'id_user' =>  ($this->user->getUser()) ? $this->user->getUser(): ID_ADMIN_CRON,
                'commentaire' => $data["commentaire"]
            );

            $this->db->insert("cmd_pl_historique", $cmd_pl_historique_data);

            foreach ($data["produit"] as $key => $value) {
                $this->db->set("qte_sla", $value[0]);
                $this->db->where("id_cmd_pl", $id);
                $this->db->where("id_produit", $key);
                $this->db->update("commandes_produit_logistique");
            }
        } else if (has_permission(CDT_PROFILE) && $data['status']!=EN_ATTENTE_DE_VALIDATION_UPR_PL) {
            $this->db->set("id_state", EN_ATTENTE_DE_VALIDATION_UPR_PL);
            $this->db->set("comment_cdt", $data["commentaire"]);
            $this->db->set("date_validation_cdt", date('Y-m-d H:i:s'));
            $this->db->where("id_cmd_pl", $id);
            $this->db->update("commandes_pl");

            $cmd_pl_historique_data = array(
                'id_cmd' => $id,
                'id_stat_cmd' => EN_ATTENTE_DE_VALIDATION_UPR_PL,
                'date_creation' => date('Y-m-d H:i:s'),
                'id_user' =>  ($this->user->getUser()) ? $this->user->getUser(): ID_ADMIN_CRON,
                'commentaire' => $data["commentaire"]
            );

            $this->db->insert("cmd_pl_historique", $cmd_pl_historique_data);

            foreach ($data["produit"] as $key => $value) {
                $this->db->set("qte_qisonu", $value[0]);
                $this->db->where("id_cmd_pl", $id);
                $this->db->where("id_produit", $key);
                $this->db->update("commandes_produit_logistique");
            }
        }
        $produits = $this->CommandeTFIModel->produitsPLByCMD($id);
        $cmd = $this->CommandeTFIModel->getCommandePL($id);
        $message = "";
        $message .= "<span style='float: left'><b> Destinataire : </b>" . $cmd->nom_prenom . "</span><br><br>";;
        $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
        $message .= "<b> Validée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . " <br><br>";
        if (!empty($data["commentaire"])) {
            $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
        }

        $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité commandée</th><th style='" . style_td_th . "' align=\"center\">Quantité validée</th></tr>";
        foreach ($produits as $key => $ele) {
            if (has_permission(CDP_PROFILE))
                $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $ele->designation . " (<i>" . $ele->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . (($cmd->id_role == 4) ? $ele->qte_upr : $ele->qte_qisonu) . "</td><td align=\"center\" style='" . style_td_th . "'>$ele->qte_upr</td></tr>";
            elseif (has_permission(CDT_PROFILE))
                $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $ele->designation . " (<i>" . $ele->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . (($cmd->id_role == 4) ? $ele->qte_upr : $ele->qte_qisonu) . "</td><td align=\"center\" style='" . style_td_th . "'>$ele->qte_qisonu</td></tr>";
            else
                $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $ele->designation . " (<i>" . $ele->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . (($cmd->id_role == 4) ? $ele->qte_upr : $ele->qte_qisonu) . "</td><td align=\"center\" style='" . style_td_th . "'>$ele->qte_sla</td></tr>";
        }
        $table .= "</table></br>";
        $message .= $table;
        $destinataire = $this->UtilisateurModel->getUser($cmd->id_user);;
        $reference = $cmd->reference_pl;
        $object = "[SLAM] Validation de commande : " . $reference . " - " . $cmd->date_creation;
        $tabCopy = array();

        $this->SendMail($destinataire, $message, $object, $tabCopy);
        $this->session->set_flashdata("success", "Votre commande est bien validée");
        $_POST["validation"] = 1;
        if($data['status']==1 && has_permission(CDT_PROFILE)){
            $sup = $this->UtilisateurModel->getSuperviseurTFI($this->session->userdata("user_id"));
            $sup = $sup->superviseur;
            $cdp = $this->UtilisateurModel->getUser($sup);
            $user_vacances=$cdp->user_vacances;
            $this->CommandeTFIModel->liste_CommandePL_ajax($cdp->id_user, $epi_outillage);
            $user= $cdp->id_user;
        }else{
            $this->CommandeTFIModel->liste_CommandePL_ajax($this->user->getUser(), $epi_outillage);
            $user=$this->user->getUser();
        }

        $others_commandes = $this->db->get()->result_array();
        if (count($others_commandes))
            if($data['status']==1 && has_permission(CDT_PROFILE) && $user!=$this->user->getUser()) {
                redirect(site_url('CommandeTFI/detail_pl_buckup/' . $others_commandes[0]["id_cmd_pl"]) . (($epi_outillage) ? "/$epi_outillage" : ""));
            }else{
                redirect(site_url('CommandeTFI/detail_pl/' . $others_commandes[0]["id_cmd_pl"]) . (($epi_outillage) ? "/$epi_outillage" : ""));
            }
        else{
            if($data['status']==1 && has_permission(CDT_PROFILE) && $user!=$this->user->getUser()) {
                redirect(site_url('CommandeTFI/listeCommandePLValidation/' . $user . '/validation') . (($epi_outillage) ? "/$epi_outillage" : ""));
            }else{
                redirect(site_url('CommandeTFI/listeCommandePL/' . $user . '/validation') . (($epi_outillage) ? "/$epi_outillage" : ""));
            }
        }
    }

    public function refuserPl($epi_outillage = null)
    {
        $data = $this->input->post();
        $id = $data["id"];
        if (has_permission(CDP_PROFILE)) {
            $this->db->set("id_state", Refus_UPR_PL);
            $this->db->set("comment_upr", $data["commentaire"]);
            $this->db->set("date_validation_upr", date('Y-m-d H:i:s'));
            $this->db->where("id_cmd_pl", $id);
            $this->db->update("commandes_pl");

            $cmd_pl_historique_data = array(
                'id_cmd' => $id,
                'id_stat_cmd' => Refus_UPR_PL,
                'date_creation' => date('Y-m-d H:i:s'),
                'id_user' =>  ($this->user->getUser()) ? $this->user->getUser(): ID_ADMIN_CRON,
                'commentaire' => $data["commentaire"]
            );

            $this->db->insert("cmd_pl_historique", $cmd_pl_historique_data);

        } else if (has_permission(SLA_PROFILE) || has_permission(SLAN_PROFILE)) {
            $this->db->set("id_state", Refus_SLA_PL);
            $this->db->set("comment_sla", $data["commentaire"]);
            $this->db->set("date_validation_sla", date('Y-m-d H:i:s'));
            $this->db->where("id_cmd_pl", $id);
            $this->db->update("commandes_pl");

            $cmd_pl_historique_data = array(
                'id_cmd' => $id,
                'id_stat_cmd' => Refus_SLA_PL,
                'date_creation' => date('Y-m-d H:i:s'),
                'id_user' =>  ($this->user->getUser()) ? $this->user->getUser(): ID_ADMIN_CRON,
                'commentaire' => $data["commentaire"]
            );

            $this->db->insert("cmd_pl_historique", $cmd_pl_historique_data);

        } else if (has_permission(CDT_PROFILE)) {
            $this->db->set("id_state", Refus_CDT_PL);
            $this->db->set("comment_cdt", $data["commentaire"]);
            $this->db->set("date_validation_cdt", date('Y-m-d H:i:s'));
            $this->db->where("id_cmd_pl", $id);
            $this->db->update("commandes_pl");

            $cmd_pl_historique_data = array(
                'id_cmd' => $id,
                'id_stat_cmd' => Refus_CDT_PL,
                'date_creation' => date('Y-m-d H:i:s'),
                'id_user' =>  ($this->user->getUser()) ? $this->user->getUser(): ID_ADMIN_CRON,
                'commentaire' => $data["commentaire"]
            );

            $this->db->insert("cmd_pl_historique", $cmd_pl_historique_data);
        }
        $produits = $this->CommandeTFIModel->produitsPLByCMD($id);
        $cmd = $this->CommandeTFIModel->getCommandePL($id);
        $message = "";
        $message .= "<span style='float: left'><b> Destinataire : </b>" . $cmd->nom_prenom . "</span><br><br>";;
        $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
        $message .= "<b> Refusée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . " <br><br>";
        if (!empty($data["commentaire"])) {
            $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
        }

        $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité commandée</th></tr>";
        foreach ($produits as $key => $ele) {
            $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $ele->designation . " (<i>" . $ele->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . (($cmd->id_role == 4) ? $ele->qte_upr : $ele->qte_qisonu) . "</td></tr>";
        }
        $table .= "</table></br>";
        $message .= $table;
        $destinataire = $this->UtilisateurModel->getUser($cmd->id_user);;
        $reference = $cmd->reference_pl;
        $object = "[SLAM] Refus de commande : " . $reference . " - " . date('d/m/Y');
        $tabCopy = array();

        $this->SendMail($destinataire, $message, $object, $tabCopy);

        $this->session->set_flashdata("success", "Votre commande est bien refusée");
        $_POST["validation"] = 1;
        $this->CommandeTFIModel->liste_CommandePL_ajax($this->user->getUser(), $epi_outillage);
        $others_commandes = $this->db->get()->result_array();
        if (count($others_commandes))
            redirect(site_url('CommandeTFI/detail_pl/' . $others_commandes[0]["id_cmd_pl"]) . (($epi_outillage) ? "/$epi_outillage" : ""));
        else
            redirect(site_url('CommandeTFI/listeCommandePL/validation') . (($epi_outillage) ? "/$epi_outillage" : ""));

    }
    public function RepasserPl($id_cmd_pl,$epi_outillage = null)
    {
        //  $data = $this->input->post();
        $id = $id_cmd_pl;
        if (has_permission(SLA_PROFILE)) {
            $this->db->set("id_state", EN_ATTENTE_DE_VALIDATION_SLA_PL);
            $this->db->set("date_validation_sla", date('Y-m-d H:i:s'));
            $this->db->where("id_cmd_pl", $id);
            $this->db->update("commandes_pl");

            $cmd_pl_historique_data = array(
                'id_cmd' => $id,
                'id_stat_cmd' => EN_ATTENTE_DE_VALIDATION_SLA_PL,
                'date_creation' => date('Y-m-d H:i:s'),
                'id_user' =>  $this->user->getUser(),
            );

            $this->db->insert("cmd_pl_historique", $cmd_pl_historique_data);

        }
        /*  $produits = $this->CommandeTFIModel->produitsPLByCMD($id);
          $cmd = $this->CommandeTFIModel->getCommandePL($id);
          $message = "";
          $message .= "<span style='float: left'><b> Destinataire : </b>" . $cmd->nom_prenom . "</span><br><br>";;
          $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
          $message .= "<b> Refusée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . " <br><br>";
          if (!empty($data["commentaire"])) {
              $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
          }

          $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité commandée</th></tr>";
          foreach ($produits as $key => $ele) {
              $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $ele->designation . " (<i>" . $ele->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . (($cmd->id_role == 4) ? $ele->qte_upr : $ele->qte_qisonu) . "</td></tr>";
          }
          $table .= "</table></br>";
          $message .= $table;
          $destinataire = $this->UtilisateurModel->getUser($cmd->id_user);;
          $reference = $cmd->reference_pl;
          $object = "[SLAM] Refus de commande : " . $reference . " - " . date('d/m/Y');
          $tabCopy = array();

          $this->SendMail($destinataire, $message, $object, $tabCopy);

         */
        $this->session->set_flashdata("success", "cette commande est Repasser en attente de validation");

        redirect(site_url('CommandeTFI/detail_pl/' . $id_cmd_pl) . (($epi_outillage) ? "/$epi_outillage" : ""));

    }

    public function getLivered()
    {
        $num = $this->CommandeTFIModel->getLivered();
        return $num;
    }


    public function refait_pl($idtfi, $id_cmd)
    {

        $commande = $this->CommandeTFIModel->getCommandePL($id_cmd);
        $produits_commande = $this->CommandeTFIModel->produitsPLByCMD($id_cmd);

        $usertfi = $this->UtilisateurModel->checkUserById($idtfi);
        $liste_produits = [];
        $liste_pack_avec_produits = [];
        $liste_produits = $this->ProduitModel->produitsLogistique();
        $add_in_tfi = false;

        $message_apres_filtre_produits = $liste_produits;

        foreach ($liste_produits as $key => $prod) {
            $produit = $this->CommandeTFIModel->getStockTFIByCommande($idtfi, $prod->id_produit);
            if ($produit) {
                $liste_produits[$key]->stock_tfi = $produit->stock_tfi;
                $liste_produits[$key]->stock_transit = $produit->stock_transit;
            } else {
                $liste_produits[$key]->stock_tfi = 0;
                $liste_produits[$key]->stock_transit = 0;
            }

        }

        //TODO
        $liste_pack_avec_produits = false;

        $depotups = $this->DepotModel->getIdDepotByUser($idtfi);
        $depotclr = $this->AdressesCLRModel->getIdAdresseByUser($idtfi);

        $this->load->view('CommandeTFI/ajouter',
            [
                "usertfi" => $usertfi,
                "liste_produits" => $liste_produits,
                "liste_packs" => $liste_pack_avec_produits,
                "message_apres_filtre_produits" => $message_apres_filtre_produits,
                "add_in_tfi" => $add_in_tfi,
                "depotups" => $depotups,
                "depotclr" => $depotclr,
                "logistique" => 1,
                "commande" => $commande,
                "epi_outillage" => null,
                "liste_commande_produits" => $produits_commande
            ]
        );
    }

    public function csv_pl()
    {
        $commandes = $this->CommandeTFIModel->csv_pl();
        var_dump($commandes);
        $path = FCPATH . "public/csv_file/";
        $file = fopen($path . "commandes_pl.csv", 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, array('Numero de commande', 'Reference', 'Crée par', 'Superviseur', 'Produit', 'Quantité validée par SLA', 'Date de validation SLA'), ";");
        foreach ($commandes as $c) {
            $array = [];
            $array = $c;
            fputcsv($file, $array, ";");


        }


        fclose($file);
    }

    public function all_cmd_extract()
    {
        $this->CommandeTFIModel->all_cmd_extract();
    }

    public function teste()
    {
        $produit_fournisseur = $this->FournisseurModel->getProdFournisseur(6, 26);
        $produit_fournisseur2 = $this->FournisseurModel->getProdFournisseur(6, 21);
        var_dump(mb_detect_encoding($produit_fournisseur->lib_condt_fournisseur, 'UTF-8'));
        var_dump(mb_detect_encoding($produit_fournisseur2->lib_condt_fournisseur, 'UTF-8', true));
        var_dump(strpos($produit_fournisseur->lib_condt_fournisseur, "kÃ©"));
        var_dump($produit_fournisseur->lib_condt_fournisseur);
        var_dump(utf8_decode($produit_fournisseur2->lib_condt_fournisseur));
    }


    public function csv_cmd()
    {
        $date_debut = $this->input->post("date_debut");
        $date_fin = $this->input->post("date_fin");
        $commandes = $this->CommandeTFIModel->csv_cmd($date_debut, $date_fin);
        $path = FCPATH . "public/csv_file/";
        $file = fopen($path . "commandes_all.csv", 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, array('Id commande', 'Reference', 'Quantité produit', 'Etat', 'Date de la derniere MAJ', 'Etat-1', "Date de la derniere MAJ", "Durée avant acquittement", "Date de création", "Date de Validation CDP", " Date premier traitement SLA (validation)","Validée par", " Date deuxième traitement SLA (Reliquat)", "Validée par", "Date expédition", "Date livraison", "Date d'acquittement", "UPR", "type commande"), ";");
        foreach ($commandes as $c) {
            $array = [];
            $array = $c;
            var_dump($c);
            fputcsv($file, $array, ";");


        }


        fclose($file);

    }


    public function logger_stocks_arg_extract()
    {
        $data = $this->CommandeTFIModel->logger_stocks_arg_extract();
        $path = FCPATH . "public/csv_file/";
        $file = fopen($path . "logger_stocks_arg_extract.csv", 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, array('Produit', 'Id produit', 'Date de modification', 'Stock avant', 'Stock après'), ";");
        foreach ($data as $d) {
            $array = [];
            $array = $d;
            var_dump($d);
            fputcsv($file, $array, ";");


        }


        fclose($file);
    }

    public function wait_validation_sla_reliquat_extract()
    {
        $cmd = $this->CommandeTFIModel->wait_validation_sla_reliquat_extract();

        $this->load->dbutil();
        $this->load->helper('download');
        $this->load->helper('file');

        $delimiter = ";";
        $newline = "\r\n";
        $enclosure = '"';
        $filename = 'wait_validation_sla_reliquat_extract.csv';

        $data = $this->dbutil->csv_from_result($cmd, $delimiter, $newline);
        force_download($filename, $data);

        /*$data = $this->CommandeTFIModel->wait_validation_sla_reliquat_extract();
        $path = FCPATH . "public/csv_file/";
        $file = fopen($path . "logger_stocks_arg_extract.csv", 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, array('Produit', 'Id produit', 'Date de modification', 'Stock avant', 'Stock après'), ";");
        foreach ($data as $d) {
            $array = [];
            $array = $d;
            var_dump($d);
            fputcsv($file, $array, ";");


        }
        fclose($file); */
    }

    public function modifierLivraison()
    {
        $id_cmd = $this->input->post('id_cmd');
        $id_livre = $this->input->post('id_livre');
        $this->CommandeTFIModel->modifierLivraison($id_cmd, $id_livre);
        redirect(site_url("CommandeTFI/detail/$id_cmd"));
    }

    public function getcountColis($id_cmd)
    {
        $array["libelle"] = "";
        $data = $this->CommandeTFIModel->getcountColisRetour($id_cmd);
        if ($data->nbrcolis == 0) {
            $array["libelle"] = "préparée";
        } else {
            $array["libelle"] = "Retour UPS";
        }
        echo $array["libelle"] . "<br>";
        var_dump($data);
    }

    public function extractCMDARG()
    {
        $date = date("Y_m_d_H_i_s");
        $date_debut = $this->input->post("date_debut");
        $date_fin = $this->input->post("date_fin");
        $commandes = $this->CommandeTFIModel->extractCMDARG($date_debut, $date_fin);
        $path = FCPATH . "public/csv_file/";
        $file = fopen($path . "commandes_arg_" . $date . ".csv", 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, array('Numero de commande', 'Reference', 'Désignation', 'Reference Free', 'Destinataire', 'Quantité', 'Type de transport', 'Etat', 'Date de préparation', 'traité / Reour'), ";");
        foreach ($commandes as $c) {
            $array = [];
            $array = $c;
            $id_cmd = $array['id_cmd'];

            if ($array["libelle"])
                array_push($array, "traité");
            else
                array_push($array, "Non traité");

            $data = $this->CommandeTFIModel->getcountColisRetour($id_cmd);
            if ($data->nbrcolis == 0) {
                if ($array["libelle"] == "Livree") {
                    $array["libelle"] = "en attente d'acquittement";
                }
                if ($array["libelle"] == "Reçue") {
                    $array["libelle"] = "terminée";
                }
                if ($array["libelle"] == "Préparee") {
                    $array["libelle"] = "préparée";
                }
            } else {
                $array["libelle"] = "Retour UPS";
            }

            fputcsv($file, $array, ";");


        }


        fclose($file);
        echo json_encode(["file_name" => "commandes_arg_" . $date . ".csv"]);


    }


    public function csvArgPrepared()
    {
        $date = date("Y_m_d_H_i_s");
        $commandes = $this->CommandeTFIModel->csvArgPrepared();
        $path = FCPATH . "public/csv_file/";
        $file = fopen($path . "csv_arg_prepared_" . $date . ".csv", 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, array('Numero de commande', 'Reference', 'Etat', 'Date de préparation'), ";");
        foreach ($commandes as $c) {
            $array = [];
            $array = $c;
            fputcsv($file, $array, ";");


        }


        fclose($file);
        echo json_encode(["file_name" => "csv_arg_prepared_" . $date . ".csv"]);


    }

    public function getOutillageMotif($id_cmd, $id_produit)
    {
        $motif = $this->CommandeTFIModel->getOutillageMotif($id_cmd, $id_produit);
        $docs = $this->CommandeTFIModel->getOutillagedocs($id_cmd, $id_produit);
        $array = ["docs" => $docs, "motifs" => $motif];
        echo json_encode($array);
    }

    public function transfert()
    {
        $data = $this->input->post();
        $cdt = $this->UtilisateurModel->getSuperviseurTFI($data["id_tech"]);
        $cdp = $this->UtilisateurModel->getSuperviseurTFI($cdt->superviseur);

        if ( !has_permission(ADMIN_PROFILE) && has_permission(CDP_PROFILE)) {
            $id_new_cdp = $this->user->getUser();
        }
        else if(!has_permission(ADMIN_PROFILE) && has_permission(CDT_PROFILE)){
            $id_new_cdp = $cdp->superviseur;
        }
        $data_demande = array(
            "id_new_cdp" => $id_new_cdp,
            "id_old_cdp" => $cdp->superviseur,
            "id_tech" => $data["id_tech"],
            "id_new_cdt" => $data["new_cdt"],
            "id_old_cdt" => $cdt->superviseur,
            "date_demande" => date("Y-m-d H:i:s"),
            "date_confirmation" => NULL,
            "state" => 1,
            "comment" => $data["comment"]
        );

        $this->CommandeTFIModel->addDemandeTransfert($data_demande);
        $message = "";
        $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
        $tech = $this->UtilisateurModel->getUser($data['id_tech']);
        $cdp = $this->UtilisateurModel->getUser($this->user->getUser());
        $old_cdp = $this->UtilisateurModel->getUser($data_demande["id_old_cdp"]);
        if ( !has_permission(ADMIN_PROFILE) && has_permission(CDP_PROFILE)) {
            $message .= "<p>Le CDP " . $cdp->nom . " " . $cdp->prenom . " à fait demande de transfert de votre technicien " . $tech->nom . " " . $tech->prenom . ".</p><p><a href='" . site_url("Utilisateur/transfertReponses/1") . "'>Répondre à la demande</a></p>";
        }
        else if( !has_permission(ADMIN_PROFILE) && has_permission(CDT_PROFILE)) {
            $message .= "<p>Le CDT " . $cdp->nom . " " . $cdp->prenom . " " . $cdp->email . " à fait demande de transfert de votre technicien " . $tech->nom . " " . $tech->prenom . ".</p><p><a href='" . site_url("Utilisateur/transfertReponses/1") . "'>Répondre à la demande</a></p>";
        }
        $tabCopy = array();
        $object = "[SLAM] Demande de transfert du technicien";
        $this->SendMail($old_cdp, $message, $object, $tabCopy);
        $this->session->set_flashdata('data', 'Votre demande a bien été envoyé.');
        redirect("Utilisateur/transfert");
    }

    public function getcommandedata($id_tech){
        $data=$this->CommandeTFIModel-> getDemande($id_tech);
        echo json_encode($data);
    }

    public function count_dt_n_v()
    {
        $demandes = $this->CommandeTFIModel->count_demande_trs_non_valide();
        echo json_encode($demandes);
    }
    public function listeDemandes($id_state = null)
    {
        $time_start = microtime(true);
        if ($this->input->post()) {

            $data = array();
            $columns = array(
                0 => 'u.nom',
                1 => 'cdp.nom',
                2 => 'tech.nom',
                3 => 'd.date_confirmation',
                4 => 'd.comment',
                5 => 's.label',

            );
            $search = $this->input->post('search')['value'];
            $limit = $this->input->post('length');
            $start = $this->input->post('start');
            $order = $columns[$this->input->post('order')[0]['column']];
            $dir = $this->input->post('order')[0]['dir'];

            $countTotalData = count($this->CommandeTFIModel->list_demandes_ajax($id_state, null, null, $order, $dir, $search));
            $totalFiltered = $countTotalData;

            $demandes = $this->CommandeTFIModel->list_demandes_ajax($id_state, $start, $limit, $order, $dir, $search);

            if ($demandes) {

                foreach ($demandes as $d) {

                    $nestedData['cdp'] = $d->cdp;
                    $nestedData['destinataire'] = $d->destinataire;
                    $nestedData['tech'] = $d->tech . "( " . $d->cdt . " )";
                    $nestedData['date'] = $d->date_demande;
                    $nestedData['commentaire'] = $d->comment;
                    $nestedData['etat'] = $d->label;
                    $nestedData['action'] = "<button class=\"btn btn-default show_demande\" data-id='$d->id'><i class=\"fa fa-eye\"></i></button>";


                    $data[] = $nestedData;
                }
                $time_stop = microtime(true) - $time_start;
                $json_data = array(
                    "draw" => intval($this->input->post('draw')),
                    "recordsTotal" => intval($countTotalData),
                    "recordsFiltered" => intval($totalFiltered),
                    "data" => $data,
                    "time"=>number_format($time_stop,2,".","").' sec'
                );

            } else {
                $time_stop = microtime(true) - $time_start;
                $json_data = array(
                    "draw" => intval($this->input->post('draw')),
                    "recordsTotal" => intval($countTotalData),
                    "recordsFiltered" => intval($totalFiltered),
                    "data" => [],
                    "time"=>number_format($time_stop,2,".","").' sec'
                );
            }

            echo json_encode($json_data);
        } else {

            $message = $this->session->flashdata('msg');
            $this->load->view('Categorie/liste', array("msg" => $message));
        }

    }


    public function csvArgColis()
    {
        $date = date("Y_m_d_H_i_s");
        $commandes = $this->CommandeTFIModel->csvArgcolis();
        $path = FCPATH . "public/csv_file/";
        $file = fopen($path . "colis_retour_arg_" . $date . ".csv", 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, array('Numero de colis', 'Code tracking', "Date d'expédition", "Réference de la commande", 'Etat', 'Date de création de la commande'), ";");
        foreach ($commandes as $c) {
            $array = [];
            $array = $c;
            fputcsv($file, $array, ";");


        }


        fclose($file);
        echo json_encode(["file_name" => "colis_retour_arg_" . $date . ".csv"]);
    }

    public function getOutillageMotifpl($id_cmd, $id_produit)
    {
        $motif = $this->CommandeTFIModel->getOutillageMotifpl($id_cmd, $id_produit);
        $docs = $this->CommandeTFIModel->getOutillagedocspl($id_cmd, $id_produit);
        $array = ["docs" => $docs, "motifs" => $motif];
        echo json_encode($array);
    }

    public function listeRexel()
    {
        $addresse_rexel=null;
        if(has_permission(TFI_PROFILE) || has_permission(CDT_PROFILE) || has_permission(CDP_PROFILE)){
            $addresse_rexel=$this->DepotFournisseurModel->getDepotRexelByUser($this->user->getUser());
        }
        $this->load->view("CommandeTFI/colis_rexel",array('rexel'=>$addresse_rexel));
    }

    public function listecommandesRexelAjax()
    {

        $time_start = microtime(true);

        $data = array();
        $input=$this->input->post();
        $columns = array(
            0 => 'c.reference',
            1 => 'cl.date_creation',
            2 => 'u.nom',
            3 => 'cl.id_statut'

        );
        $search = $this->input->post('search')['value'];
        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $order = $columns[$this->input->post('order')[0]['column']];
        $dir = $this->input->post('order')[0]['dir'];
        // list_commandes_rexel_ajax($id_user,$id_status,$start = null, $limit = null, $order, $dir, $search = null)
        $countTotalData = count($this->CommandeTFIModel->list_commandes_rexel_ajax($input['userid'],$input['id_statut'],null, null, $order, $dir, $search));
        $totalFiltered = $countTotalData;

        $colis = $this->CommandeTFIModel->list_commandes_rexel_ajax($input['userid'],$input['id_statut'],$start, $limit, $order, $dir, $search);

        if ($colis) {

            foreach ($colis as $c) {

                $nestedData['reference'] = $c->reference;
                $nestedData['date_creation'] = date_fr($c->date_creation);
                $nestedData['cree_par'] = $c->destinataire;
                $nestedData['etat'] = $c->nom;
                $nestedData['action'] = "<button class=\"btn btn-default show_colis\" data-id='$c->id_colis'><i class=\"fa fa-eye\"></i></button>";


                $data[] = $nestedData;
            }
            $time_stop = microtime(true) - $time_start;
            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval($countTotalData),
                "recordsFiltered" => intval($totalFiltered),
                "data" => $data,
                "time" => number_format($time_stop,2,".","").' sec'
            );

        } else {
            $time_stop = microtime(true) - $time_start;
            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval($countTotalData),
                "recordsFiltered" => intval($totalFiltered),
                "data" => [],
                "time" => number_format($time_stop,2,".","").' sec'
            );
        }

        echo json_encode($json_data);

    }


    public function listeRexelAjax()
    {

        $time_start = microtime(true);

        $data = array();
        $columns = array(
            0 => 'c.reference',
            1 => 'cl.date_creation',
            2 => 'u.nom',
            3 => 'cl.id_statut'

        );
        $search = $this->input->post('search')['value'];
        $limit = $this->input->post('length');
        $start = $this->input->post('start');
        $order = $columns[$this->input->post('order')[0]['column']];
        $dir = $this->input->post('order')[0]['dir'];

        $countTotalData = count($this->CommandeTFIModel->list_rexel_ajax(null, null, $order, $dir, $search));
        $totalFiltered = $countTotalData;

        $colis = $this->CommandeTFIModel->list_rexel_ajax($start, $limit, $order, $dir, $search);

        if ($colis) {

            foreach ($colis as $c) {

                $nestedData['commande'] = $c->reference;
                $nestedData['date_creation'] = date_fr($c->date_creation);
                $nestedData['cree_par'] = $c->destinataire;
                $nestedData['etat'] = $c->nom;

                if($c->id_statut == LIVRE_COLIS_REXEL AND has_permission(TFI_PROFILE)) {

                    $nestedData['action'] = "<a class=\"btn btn-default\" href='" . site_url("CommandeTFI/detail/" . $c->id_cmd) . "'> <i class=\"fa fa-eye\"></i></a>";
                }

                else {
                    $nestedData['action'] = "<button class=\"btn btn-default show_colis\" data-id='$c->id_colis'><i class=\"fa fa-eye\"></i></button>";
                }

                $data[] = $nestedData;
            }
            $time_stop = microtime(true) - $time_start;
            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval($countTotalData),
                "recordsFiltered" => intval($totalFiltered),
                "data" => $data,
                "time" => number_format($time_stop,2,".","").' sec'
            );

        } else {
            $time_stop = microtime(true) - $time_start;
            $json_data = array(
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => intval($countTotalData),
                "recordsFiltered" => intval($totalFiltered),
                "data" => [],
                "time" => number_format($time_stop,2,".","").' sec'
            );
        }

        echo json_encode($json_data);

    }


    public function getRexelById($id)
    {
        $data = $this->CommandeTFIModel->getRexelById($id);
        $adreese = $this->CommandeTFIModel->getRexelColisAdresse($id);
        $produits = $this->CommandeTFIModel->getRexelProduitsById($id);
        echo json_encode(array($data, $produits , $adreese));
    }

    public function livrerRexel($id)
    {
        $date = date("Y_m_d_H_i_s");
        $this->db->update("colis_rexel", ["id_statut" => 2, "date_livraison" => $date], ["id_colis" => $id]);
        $colis_historique_data = array(
            'id_colis' => $id,
            'id_statut_colis' => 2,
            'date_statut' => $date,
            'type_colis' => 2,
            'id_user' => $this->user->getUser(),
            'commentaire' =>  null
        );

        $this->db->insert("colis_historique", $colis_historique_data);

        $id_cmd = $this->CommandeTFIModel->getRexelById($id)->id_cmd;
        $produits =  $this->CommandeTFIModel->getProduitsByCommande($id_cmd);

        if(!count($produits)) {

            $row = $this->CommandeTFIModel->checkCommandeByColisRexelStatut($id_cmd, LIVRE_COLIS_REXEL);

            if($row) {

                $cmd_tfi_historique_data = array(
                    'id_cmd' => $id_cmd,
                    'id_statcmd' => LIVRE,
                    'cree_par' => $this->user->getUser(),
                    'date_creation' => $date,
                    'commentaire' => ''
                );

                $this->db->insert("commande_tfi_historique", $cmd_tfi_historique_data);
            }
        }

        $this->sendMailLivreColisRexel($id);
        redirect(site_url("CommandeTFI/listeRexel"));
    }

    public function sendMailLivreColisRexel($id){
        $message="";
        $getlistProduitrexel=$this->CommandeTFIModel->getRexelProduitsById($id);
        //  var_dump($getlistProduitrexel);
        $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité Livrée</th></tr>";
        foreach ($getlistProduitrexel as $produit) {
            $id_cmd=$produit->id_cmd;
            $table .= "<tr><td style='" . style_td_th_produit . "' align=\"left\">" . $produit->designation . " (<i>" . $produit->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . $produit->quantite_initial . "</td></tr>";
        }
        $table .= "</table><br>";
        $message .= $this->infoCommande($id_cmd);
        $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
        $message .= "<b> Livrée par : </b>" . $cree_par->prenom . " " . $cree_par->nom . "  <br><br>";
        $destinataire = $this->getDestinataire($id_cmd);
        $reference = $this->CommandeTFIModel->getInfoCommande($id)->reference;

        $object = "[SLAM] Colis rexel : " . $reference . " - " . date('d/m/Y');

        $tabCopy = array();
        $message.=$table;

        $this->SendMail($destinataire, $message, $object, $tabCopy);
    }

    public function recuRexel($id)
    {
        $data = $this->input->post("data");
        $date = date("Y_m_d_H_i_s");
        $this->db->update("colis_rexel", ["id_statut" => 3, "date_reception" => $date], ["id_colis" => $id]);
        $colis_historique_data = array(
            'id_colis' => $id,
            'id_statut_colis' => 3,
            'date_statut' => $date,
            'type_colis' => 2,
            'id_user' => $this->user->getUser(),
            'commentaire' =>  null
        );

        $this->db->insert("colis_historique", $colis_historique_data);

        $id_cmd = $this->CommandeTFIModel->getRexelById($id)->id_cmd;
        $produits =  $this->CommandeTFIModel->getProduitsByCommande($id_cmd);

        if(!count($produits)) {

            $row = $this->CommandeTFIModel->checkCommandeByColisRexelStatut($id_cmd, RECU_COLIS_REXEL);

            if($row) {

                $cmd_tfi_historique_data = array(
                    'id_cmd' => $id_cmd,
                    'id_statcmd' => RECEIVED,
                    'cree_par' => $this->user->getUser(),
                    'date_creation' => $date,
                    'commentaire' => ''
                );

                $this->db->insert("commande_tfi_historique", $cmd_tfi_historique_data);
            }
        }

        foreach ($data as $produit)
        {
            $this->db->update("colis_rexel_produit", ["quantite_recu" => $produit["quantite"]], ["id_colis" => $id ,"id_produit"=>$produit["id_produit"]]);
        }
        echo 1;
    }

    public function validerRexel($id)
    {
        $data = $this->input->post();
        var_dump($data);
        //    die();
        $colis_rexel = array("date_creation" => date("Y-m-d h:i:s"), "cree_par" => $this->user->getUser(), "id_statut" => 1, "id_cmd" => $id,"largeur"=>$data["largeur"],"poids"=>$data["poids"],"longueur"=>$data["longeur"],"hauteur"=>$data["hauteur"],"comment"=>$data["comment"]);
        $id_colis_rexel = $this->CommandeTFIModel->createRexelColis($colis_rexel);
        foreach ($data["quantite"] AS $key=>$value) {
            if ($value>0)
            {
                $produit =  $this->CommandeTFIModel->getProduitByCommandeAndId($id,$key);
                if($produit->quantite == $value)
                {
                    $this->CommandeTFIModel->deleteProduitCommandeByRexel($key,$id);
                }
                else
                {
                    $new_value = $produit->quantite - $value;
                    $this->CommandeTFIModel->editProduitCommandeByRexel($key,$id,$new_value);

                }
                $colis_rexel_elements = array("id_colis" => $id_colis_rexel, "id_produit" => $key, "quantite_initial" => $value, "quantite" => 0, "quantite_recu" => 0);
                $this->CommandeTFIModel->createRexelColisElements($colis_rexel_elements);
            }

        }

        $produits =  $this->CommandeTFIModel->getProduitsByCommande($id);
        if(!count($produits))
        {
            $data_stat["id_cmd"] = $id;
            $data_stat["id_statcmd"] = VALIDE;
            $data_stat["cree_par"] = $this->user->getUser();
            $data_stat["date_creation"] = date('Y-m-d H:i:s');

            $this->CommandeTFIModel->changeStatus($data_stat);
            $data_livre = array("id_cmd"=>$id,"type_livree"=>8);
            $this->LivrerModel->ajouterLivrer($data_livre);
        }

        redirect(site_url("CommandeTFI/detail/$id"));
    }


    public function extractUsers()
    {
        $data = $this->CommandeTFIModel->extractUsers();
        $path = FCPATH . "public/csv_file/";
        $file = fopen($path . "extract_users.csv", 'w');
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, array('Prenom', 'Nom', 'Email', 'Active' , 'id_agent_sin3', 'Poste','Rôle','Email buckup 1','Email buckup 2','Email buckup 3','UPR','Adresse CLR','Dépôt UPS','Nom du dépôt UPS','adresse du dépôt UPS','code postal du dépôt UPS','Ville du dépôt UPS'), ";");
        foreach ($data as $d) {
            $array = [];
            $array = $d;
            var_dump($d);
            fputcsv($file, $array, ";");


        }


        fclose($file);
    }


    public function do_upload_outillage($dataImage,$newimage,$vol=null)
    {
        $source_path = $dataImage;
        $target_path = $newimage;
        if($vol)
        {
            $config_manip = array(
                'image_library' => 'gd2',
                'source_image' => $source_path,
                'new_image' => $target_path,
                'maintain_ratio' => true,
                'create_thumb' => TRUE,
                'thumb_marker' => '',
                'width' => 1200
            );
        }
        else
        {
            $config_manip = array(
                'image_library' => 'gd2',
                'source_image' => $source_path,
                'new_image' => $target_path,
                'maintain_ratio' => false,
                'create_thumb' => TRUE,
                'thumb_marker' => '',
                'width' => 600,
                'height'=>600
            );
        }




        $this->image_lib->clear();
        $this->image_lib->initialize($config_manip);
        if (!$this->image_lib->resize()) {
            echo $this->image_lib->display_errors();
            die();
        }


        $this->image_lib->clear();
        unlink($dataImage);
        return 1;

    }


    public function getOutillagehisto($id_cmd,$id_produit)
    {
        $cmd = $this->CommandeTFIModel->getCommandeById($id_cmd);
        $data = $this->CommandeTFIModel->getOutillagehisto($cmd->id_user,$id_produit);

        var_dump($this->db->last_query());
        die();
        echo json_encode($data);
    }

    public function ajax_list_track_man()
    {
        $time_start = microtime(true);
        $data = $this->input->post();

        $list = $this->CommandeTFIModel->get_datatables_track_man($data);
        $result_data = array();
        $no = $_POST['start'];
        if (count($list) > 0)
            foreach ($list as $colis) {

                $no++;
                $row = array();

                $row['num_cmd_tracking'] = $colis->num_cmd_tracking;
                $row['expediteur'] = $colis->expediteur;
                $row['destinataire'] = $colis->destinataire;
                $row['date_expedition'] = $colis->date_expedition;
                $row['contenu_colis'] = $colis->contenu_colis;
                $row['poids'] = $colis->poids;


                $result_data[] = $row;
            }
        //}

        $time_stop = microtime(true) - $time_start;

        $output = array(
            "draw" => $_POST['draw'],
            "recordsTotal" => $this->UtilisateurModel->count_all($data),
            "recordsFiltered" => $this->UtilisateurModel->count_filtered($data),
            "data" => $result_data,
            "time" => number_format($time_stop,2,".","").' sec'
        );

        echo json_encode($output);

    }

    public function send_reclamation_colis()
    {
        $data = $this->input->post();
        var_dump($data);

        $target_dir = realpath("./uploads/ups_perdu/");
        foreach ($_FILES["userFiles"]["tmp_name"] as $key => $value) {
            if (count($value)) {

                $vol = false;
                if ($data["motif_id"][$key][0] == 3) {
                    $vol=true;
                }
                foreach ($value as $key2 => $value2) {

                    if ($value2) {
                        $name = $_FILES["userFiles"]["name"][$key][$key2];
                        $array = (explode(".", $name));
                        $ext = end($array);
                        $target_file = $target_dir . "/" . "prop".$this->user->getUser() . "_" . $id_cmd . "_" . $key . "_" . $key2 . "." . $ext;
                        $last_name = $target_dir . "/" . $this->user->getUser() . "_" . $id_cmd . "_" . $key . "_" . $key2 . "." . $ext;
                        move_uploaded_file($value2, $target_file);
                        $this->do_upload_outillage("$target_file","$last_name",$vol);

                        $this->db->insert("justif_spe_outillage", array("id_produit" => $key, "url" => $this->user->getUser() . "_" . $id_cmd . "_" . $key . "_" . $key2 . "." . $ext, "id_cmd_pl" => $id_cmd));
                    }

                }
            }
        }
        /*$this->db->trans_start();
        $id_colis = $data["id_colis"];
        $idtfi = $data["idtfi"];
        if (isset($data["commentaire"]))
            $commentaire = $data["commentaire"];

        $colis_temp = $this->CommandeTFIModel->getColisById($id_colis);
        $data_colis["id_statutcolis"] = PERDU_UPS;
        $data_colis["date_reception"] = date('Y-m-d H:i:s');
        if (isset($commentaire))
            $data_colis["comment_reception"] = $commentaire;
        $this->CommandeTFIModel->changeStatusColis($id_colis, $data_colis);

        $liste_colis_produit = $data['productsToAdd'];

        foreach ($liste_colis_produit as $key => $colis) {
            $prod_tfi = $this->CommandeTFIModel->getProdTFI($colis_temp->id_cmd, $idtfi, $colis['id_produit'], $colis['reference']);

            if ($prod_tfi) {
                $data_update["stock_tfi"] = $prod_tfi->stock_tfi + $colis['quantite'];
                $stock_transit = $prod_tfi->stock_transit - $colis['ancien_quantite'];
                if ($stock_transit >= 0) {
                    $data_update["stock_transit"] = $stock_transit;
                }
                $this->CommandeTFIModel->updateStockTFI($prod_tfi->id, $data_update);
            } else {
                $data_insert["id_produit"] = $colis['id_produit'];
                $data_insert["id_user"] = $idtfi;
                $data_insert["id_cmd"] = $colis_temp->id_cmd;
                $data_insert["stock_tfi"] = $colis['quantite'];
                $this->CommandeTFIModel->insertStockTFI($data_insert, '+');
            }
            if ($prod_tfi->id_categorie == ID_PRODUIT_COUTEUX || $prod_tfi->id_categorie == ID_PRODUIT_EPISPE) {
                $data_rma_historique["id_user"] = $idtfi;
                $data_rma_historique["id_etat_validation_rma"] = EN_SERVICE;
                $data_rma_historique["date_creation"] = date('Y-m-d');
                $data_rma_historique["id_produit_tfi"] = $prod_tfi->id;
                $this->db->insert('rma_historique', $data_rma_historique);
                $data_update_produit_tfi["id_etat_rma"] = EN_SERVICE;
                $data_update_produit_tfi["id_etat_validation_rma"] = EN_SERVICE;
                $this->CommandeTFIModel->updateStockTFI($prod_tfi->id, $data_update_produit_tfi);
            }
            if ($colis['reference'] != null && !empty($colis['reference']))
                $this->CommandeTFIModel->updateElementColis(array('quantite_recue' => $colis['quantite']), array('id_colis' => $id_colis, 'id_produit' => $colis['id_produit'], 'reference' => $colis['reference']));
            else
                $this->CommandeTFIModel->updateElementColis(array('quantite_recue' => $colis['quantite']), array('id_colis' => $id_colis, 'id_produit' => $colis['id_produit']));
        }


        $id_cmd = $this->CommandeTFIModel->getCmdByColis($id_colis)->id_cmd;
        $liste_commande_produit = $this->CommandeTFIModel->listeCommandeProduit($id_cmd);
        $qte_total_colis = 0;
        $qte_total_cmd = 0;

        foreach ($liste_commande_produit as $key => $cmd) {
            $qte_total_colis += $this->CommandeTFIModel->getQteColisByProd($id_cmd, $cmd->id_produit);
            $qte_total_cmd += $this->CommandeTFIModel->getQteCmdByProd($id_cmd, $cmd->id_produit);
        }

        $shipped_colis = $this->CommandeTFIModel->getLivredColisByCmd($id_cmd);
        if ($shipped_colis == 0 && $qte_total_cmd == $qte_total_colis) {
            $data_statut["id_cmd"] = $id_cmd;
            $data_statut["id_statcmd"] = RECEIVED;
            $data_statut["cree_par"] = $this->user->getUser();
            $data_statut["date_creation"] = date('Y-m-d H:i:s');
            $this->CommandeTFIModel->changeStatus($data_statut);
        }

        echo json_encode(['erreur' => '0', 'reponse' => 'Le Colis à été Recus avec succès !!', 'id_colis' => $colis_temp->id_cmd]);
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $message = "";
            $message .= $this->infoCommande($id_cmd);
            $cree_par = $this->UtilisateurModel->getUser($this->user->getUser());
            $message .= "<b>Reçue par : </b>" . $cree_par->prenom . " " . $cree_par->nom . "<br><br>";
            if (!empty($data["commentaire"])) {
                $message .= "<b> Commentaire : </b>" . $data["commentaire"] . "<br><br>";
            }
            $table = "<table border=\"1\" style='" . style_table . "' width='100%'><tr style='" . tr . "'><th style='" . style_td_th_produit . "' align=\"left\">Produit</th><th style='" . style_td_th . "' align=\"center\">Quantité expédiée</th><th style='" . style_td_th . "' align=\"center\">Quantité reçue</th></tr>";
            foreach ($liste_colis_produit as $key => $colis) {
                $table .= "<tr><td align=\"left\" style='" . style_td_th_produit . "'>" . $this->ProduitModel->getInfoProduit($colis['id_produit'])->designation . " (<i>" . $this->ProduitModel->getInfoProduit($colis['id_produit'])->reference_free . "</i>)" . "</td><td style='" . style_td_th . "' align=\"center\">" . $colis['ancien_quantite'] . "</td><td style='" . style_td_th . "' align=\"center\">" . $colis['quantite'] . "</td></tr>";
            }
            $table .= "</table><br>";
            if (count($liste_colis_produit) > 0) {
                $message .= $table;
            }
            $destinataire = $this->getDestinataire($id_cmd);
            $reference = $this->CommandeTFIModel->getInfoCommande($id_cmd)->reference;
            $object = "[SLAM] - Colis reçu acquitté " . $reference . " - " . date('d/m/Y');
            $tabCopy = array();
            if ($idtfi != $this->user->getUser()) {
                $this->SendMail($destinataire, $message, $object, $tabCopy);
            }
        }*/


    }
}
