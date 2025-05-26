// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent } from "react";
import {
  FormContentInterface,
  FormInterface,
  TableLineItemInterface,
} from "../../../interface/InputInterface";
import {
  DefaultFormState,
  getDefaultValue,
  getFlatFields,
  handleChangeInputGeneric,
  handleChangeSelectGeneric,
} from "../../../functions/form";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import { getListObjectSageMetadata } from "../../../functions/getMetadata";
import { getTranslations } from "../../../functions/translations";
import { FormInput } from "../../component/form/FormInput";
import { FArtfournisseInterface } from "../../../interface/FArticleInterface";
import { MetadataInterface } from "../../../interface/WordpressInterface";
import { AfPrincipalInput } from "../../component/form/fArticle/AfPrincipalInput";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

export const ArticleFournisseursComponent = React.forwardRef((props, ref) => {
  const handleChange =
    (prop: keyof DefaultFormState) =>
    (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeInputGeneric(event, prop, setValues);
    };

  const handleChangeSelect =
    (prop: keyof DefaultFormState) =>
    (event: ChangeEvent<HTMLSelectElement>) => {
      handleChangeSelectGeneric(event, prop, setValues);
    };

  const prefix = "fArtfournisses";

  const [selectedCtNum, setSelectedCtNum] = React.useState<string>(() => {
    const fArtfournisses: FArtfournisseInterface[] = getListObjectSageMetadata(
      prefix,
      articleMeta,
      "ctNum",
    );
    return (
      fArtfournisses.find((x) => x.afPrincipal.toString() === "1")?.ctNum ?? ""
    );
  });
  const [afPrincipalRefs, setAfPrincipalRefs] = React.useState<any[]>([]);

  const [fArtfournisses] = React.useState<FArtfournisseInterface[]>(
    getListObjectSageMetadata(prefix, articleMeta, "ctNum"),
  );
  const [form] = React.useState<FormInterface>(() => {
    const formContent: FormContentInterface[] = [
      {
        props: {
          container: true,
          spacing: 1,
          sx: { p: 1 },
        },
        children: [
          {
            props: {
              size: { xs: 12 },
            },
            table: {
              headers: [
                translations.words.supplier,
                translations.words.main,
                translations.words.supplierRef,
                translations.words.buyPrice,
              ],
              items: fArtfournisses.map(
                (fArtclient): TableLineItemInterface => {
                  const refAfPrincipal = React.createRef();
                  setAfPrincipalRefs((x) => [...x, refAfPrincipal]);
                  return {
                    item: fArtclient,
                    lines: [
                      {
                        Dom: <>{fArtclient.ctNum}</>,
                      },
                      {
                        Dom: (
                          <AfPrincipalInput
                            defaultCtNum={selectedCtNum}
                            ctNum={fArtclient.ctNum}
                            setSelectedCtNumParent={setSelectedCtNum}
                            ref={refAfPrincipal}
                          />
                        ),
                      },
                      {
                        field: {
                          name:
                            prefix + "[" + fArtclient.ctNum + "].afRefFourniss",
                          DomField: FormInput,
                          hideLabel: true,
                        },
                      },
                      {
                        field: {
                          name: prefix + "[" + fArtclient.ctNum + "].afPrixAch",
                          DomField: FormInput,
                          hideLabel: true,
                        },
                      },
                    ],
                  };
                },
              ),
            },
          },
        ],
      },
    ];
    const flatFields = getFlatFields(formContent);
    return {
      content: formContent,
      flatFields: flatFields,
      fieldNames: flatFields.map((f) => f.name),
    };
  });

  const [values, setValues] = React.useState(getDefaultValue(form));

  React.useEffect(() => {
    for (const afPrincipalRef of afPrincipalRefs) {
      afPrincipalRef.current.onParentFormChange(selectedCtNum);
    }
  }, [selectedCtNum]);

  return (
    <FormContentComponent
      content={form.content}
      values={values}
      handleChange={handleChange}
      transPrefix="fArticles"
      handleChangeSelect={handleChangeSelect}
    />
  );
});
