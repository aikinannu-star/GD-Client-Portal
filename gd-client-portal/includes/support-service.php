<?php
if(!defined('ABSPATH')) exit;

/** Business boundary for Support ticket lifecycle and SLA transitions. */
if(!class_exists('GDCP_Support_Service')):
final class GDCP_Support_Service {
    private $repo;
    public function __construct($repo=null){ $this->repo=$repo?:new GDCP_Support_Repository(); }
    public function create_ticket($tenant,$user,$project_id,$subject,$body,$category,$priority){
        $days=$priority==='urgent'?1:($priority==='high'?2:($priority==='normal'?3:5));
        $now=current_time('mysql'); $due=gmdate('Y-m-d H:i:s',time()+$days*DAY_IN_SECONDS);
        $id=$this->repo->create_ticket(array('tenant_id'=>absint($tenant),'user_id'=>absint($user),'project_id'=>absint($project_id),'subject'=>$subject,'category'=>$category,'priority'=>$priority,'status'=>'open','due_at'=>$due,'created_at'=>$now,'updated_at'=>$now,'last_response_at'=>$now));
        if(!$id)return 0;
        $message=$this->repo->create_message(array('ticket_id'=>$id,'tenant_id'=>absint($tenant),'author_id'=>absint($user),'body'=>$body,'is_internal'=>0,'created_at'=>$now));
        if(!$message){ $this->repo->update_ticket($id,array('status'=>'closed','updated_at'=>$now,'closed_at'=>$now)); return 0; }
        return $id;
    }
    public function reply($ticket,$author,$body,$manager,$internal=false){
        $now=current_time('mysql'); $message=$this->repo->create_message(array('ticket_id'=>absint($ticket->id),'tenant_id'=>absint($ticket->tenant_id),'author_id'=>absint($author),'body'=>$body,'is_internal'=>$internal?1:0,'created_at'=>$now));
        if(!$message)return false;
        $status=$manager?'waiting_client':'in_progress';
        return $this->repo->update_ticket($ticket->id,array('status'=>$status,'updated_at'=>$now,'last_response_at'=>$now));
    }
    public function update_ticket($ticket,$status,$priority,$assignee){
        $closed=in_array($status,array('resolved','closed'),true)?current_time('mysql'):null;
        return $this->repo->update_ticket($ticket->id,array('status'=>$status,'priority'=>$priority,'assigned_to'=>absint($assignee),'updated_at'=>current_time('mysql'),'closed_at'=>$closed));
    }
    public function resolve($ticket){ return $this->update_ticket($ticket,'resolved',$ticket->priority,$ticket->assigned_to); }
    public function escalate($ticket){ return $this->repo->update_ticket($ticket->id,array('due_at'=>gmdate('Y-m-d H:i:s',time()+DAY_IN_SECONDS),'priority'=>'urgent','updated_at'=>current_time('mysql'))); }
}
endif;
