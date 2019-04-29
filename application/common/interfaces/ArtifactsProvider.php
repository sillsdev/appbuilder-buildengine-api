<?php

namespace common\interfaces;

interface ArtifactsProvider
{
  public function beginArtifacts($publicBaseUrl);
  public function handleArtifact($destinationFile, $fileContents);
  public function getBasePrefixUrl($productStage);
  public function artifactType($key);
}