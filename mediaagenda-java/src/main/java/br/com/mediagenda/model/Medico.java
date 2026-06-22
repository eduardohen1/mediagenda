package br.com.mediagenda.model;

public class Medico{
    //Argumentos
    private Long id;
    private String nome;
    private String crm;
    private Long especialidadeId;
    //Construtor
    public Medico(){}
    public Medico(Long id, String nome, String crm, Long especialidadeId){
        this.id = id;
        this.nome = nome;
        this.crm = crm;
        this.especialidadeId = especialidadeId;
    }

    public Long getId(){
        return this.id;
    }
    public void setId(Long id){
        this.id = id;
    }
    public String getNome(){
        return this.nome;
    }
    public void setNome(String nome){
        this.nome = nome;
    }
    public String getCrm(){
        return this.crm;
    }
    public void setCrm(String crm){
        this.crm = crm;
    }
    public Long getEspecialidadeId(){
        return this.especialidadeId;
    }
    public void setEspecialidadeId(Long especialidadeId){
        this.especialidadeId = especialidadeId;
    }


}