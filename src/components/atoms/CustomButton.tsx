interface CustomButtonProps{
    title: string;
    onClickFunction: ()=>void;
}
export default function CustomButton(
    {
        title,
        onClickFunction
    }: CustomButtonProps
){
    return(
        <>
            <button 
            type="submit" 
            className="px-4 py-2 bg-primary text-white rounded-lg cursor-pointer hover:bg-black transition-all ease-in-out"
            onClick={onClickFunction}
            >
            {title}
            </button>
        </>
    )
}