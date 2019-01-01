<?php

class ProposalController extends BaseApiController
{

    public function getProposal(Request $request, PDO $db)
    {
        $this->request = $request;
        $this->db      = $db;

        $this->setDbAndRequest($db, $request);
        $proposalId = $this->getItemId($request);

        $verbose = $this->getVerbosity($request);

        $proposal = $this->getTalkById($request, $db, $talk_id, $verbose);
        $collection = new TalkModelCollection([$talk], 1);

        return $collection->getOutputView($request, $verbose);
    }

    public function addProposal(Request $request, PDO $db)
    {
        $this->setDbAndRequest($db, $request);
        $talk_id = $this->getItemId($request);
        $verbose = $this->getVerbosity($this->request);

        // pagination settings
        $start          = $this->getStart($this->request);
        $resultsperpage = $this->getResultsPerPage($this->request);

        $comment_mapper = $this->getMapper('talkcomment');

        return $comment_mapper->getCommentsByTalkId($talk_id, $resultsperpage, $start, $verbose);
    }

    public function editProposal(Request $request, PDO $db)
    {
        $this->setDbAndRequest($db, $request);
        $talk_id = $this->getItemId($request);
        $mapper = $this->getMapper('talk');

        return $mapper->getUserStarred($talk_id, $this->request->user_id);
    }

    public function convertProposalToTalk(Request $request, PDO $db)
    {
        if (!isset($request->parameters['title'])) {
            throw new Exception('Generic talks listing not supported', 405);
        }

        $this->setDbAndRequest($db, $request);

        $keyword = filter_var(
            $request->parameters['title'],
            FILTER_SANITIZE_STRING,
            FILTER_FLAG_NO_ENCODE_QUOTES
        );

        $verbose = $this->getVerbosity($this->request);

        $start          = $this->getStart($this->request);
        $resultsperpage = $this->getResultsPerPage($this->request);

        $mapper = $this->getMapper('talk');
        $talks = $mapper->getTalksByTitleSearch($keyword, $resultsperpage, $start);

        return $talks->getOutputView($this->request, $verbose);
    }


}
