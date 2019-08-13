<?php

class Conlabz_CrConnect_Test_Config_ActivationXml extends EcomDev_PHPUnit_Test_Case_Config
{
    /**
     * @test
     */
    public function codePool()
    {
        $this->assertModuleCodePool('community');
    }
}
