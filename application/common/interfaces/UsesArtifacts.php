<?php

namespace common\interfaces;

interface UsesArtifacts
{
  public function beginArtifacts($publicBaseUrl);
  public function handleArtifact($destinationFile, $fileContents);
  public function getBasePrefixUrl($productStage);
}